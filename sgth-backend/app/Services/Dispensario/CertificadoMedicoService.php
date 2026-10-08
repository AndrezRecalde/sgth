<?php

namespace App\Services\Dispensario;

use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\CertificadoMedico;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Services\Asistencia\AprobacionCertificadoSirha7Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Emitir, anular e imprimir los certificados médicos del dispensario.
 *
 * Desde el 2026-10-08 emitir ya no crea un permiso de Asistencia: Talento
 * Humano y Trabajo Social aprueban el certificado mismo y lo registran en
 * Sirha7 (`AprobacionCertificadoSirha7Service`). El permiso automático guardaba
 * solo el primer día y se saltaba las reglas de los permisos.
 */
class CertificadoMedicoService
{
    public function __construct(private AprobacionCertificadoSirha7Service $sirha7) {}

    public const DIAS_MAX_REPOSO = 3;

    public function emitir(array $datos, int $emisorId): CertificadoMedico
    {
        return DB::transaction(function () use ($datos, $emisorId) {
            $dias = (int) $datos['dias_reposo'];

            if ($dias < 1 || $dias > self::DIAS_MAX_REPOSO) {
                throw new ReglaNegocioException(
                    'El máximo de días de reposo que puede ' .
                    'otorgar un médico es de ' .
                    self::DIAS_MAX_REPOSO . ' días.'
                );
            }

            $consulta = ConsultaMedica::with('historiaClinica')
                ->findOrFail($datos['consulta_medica_id']);

            $historia = $consulta->historiaClinica;
            $esServidor = $historia && $historia->servidor_id;

            $fechaInicio = Carbon::parse(
                $datos['fecha_inicio'] ?? $consulta->fecha_consulta
            );
            $fechaFin = isset($datos['fecha_fin'])
                ? Carbon::parse($datos['fecha_fin'])
                : $fechaInicio->copy()->addDays($dias - 1);

            $diasCalculados = $fechaInicio->diffInDays($fechaFin) + 1;
            if ($diasCalculados > self::DIAS_MAX_REPOSO) {
                throw new ReglaNegocioException(
                    'El rango seleccionado excede el máximo de ' .
                    self::DIAS_MAX_REPOSO . ' días de reposo.'
                );
            }

            // Nadie tiene dos reposos a la vez. Con la historia bloqueada, dos
            // emisiones simultáneas para el mismo paciente no se cuelan.
            HistoriaClinica::lockForUpdate()->find($historia?->id);
            $this->rechazarSiSeSolapa($consulta, $fechaInicio, $fechaFin);

            $certificado = CertificadoMedico::create([
                'consulta_medica_id'   => $consulta->id,
                'servidor_id'          => $esServidor ? $historia->servidor_id : null,
                'emitido_por'          => $emisorId,
                'dias_reposo'          => $dias,
                'fecha_inicio'         => $fechaInicio,
                'fecha_fin'            => $fechaFin,
                'diagnostico_cie10_id' => $datos['diagnostico_cie10_id'] ?? null,
                'observaciones'        => $datos['observaciones'] ?? null,
                'folio'                => $this->generarFolioCertificado(),
                'tipo_paciente'        => $esServidor ? 'servidor' : 'beneficiario',
                'created_by'           => $emisorId,
            ]);

            return $certificado->load([
                'consultaMedica',
                'emisor',
                'diagnosticoCie10',
            ]);
        });
    }

    /**
     * Un certificado no se emite sobre días que ya cubre otro del mismo
     * paciente: dos reposos a la vez no existen, y para un servidor serían
     * dos aprobaciones y dos registros en Sirha7 del mismo día. Los anulados
     * no cuentan.
     */
    private function rechazarSiSeSolapa(ConsultaMedica $consulta, Carbon $inicio, Carbon $fin): void
    {
        $otro = CertificadoMedico::query()
            ->whereNull('anulado_en')
            ->whereHas('consultaMedica', fn ($q) => $q->where('historia_clinica_id', $consulta->historia_clinica_id))
            ->whereDate('fecha_inicio', '<=', $fin->toDateString())
            ->whereDate('fecha_fin', '>=', $inicio->toDateString())
            ->orderBy('fecha_inicio')
            ->first();

        if ($otro) {
            throw new ReglaNegocioException(sprintf(
                'El paciente ya tiene el certificado %s, con reposo del %s al %s: '.
                'los días se cruzan. Anúlelo si hay que corregirlo, o elija otras fechas.',
                $otro->folio,
                $otro->fecha_inicio->format('d/m/Y'),
                $otro->fecha_fin->format('d/m/Y'),
            ));
        }
    }

    /**
     * Anula un certificado y, si ya se registró en Sirha7, lo retira de allí.
     *
     * Sirha7 va primero, con la fila del certificado bloqueada: si se niega o
     * no responde, el certificado no se anula, porque los días de reposo no
     * pueden quedar justificando una ausencia en el biométrico. Lo aprobado a
     * mano no se toca: el SGTH no lo escribió.
     *
     * No hay plazo. Un certificado equivocado hay que poder corregirlo aunque
     * los días de reposo ya hayan pasado.
     */
    public function anular(
        int $id,
        string $motivo,
        int $anuladoPor
    ): CertificadoMedico {
        return DB::transaction(function () use ($id, $motivo, $anuladoPor) {
            $certificado = CertificadoMedico::lockForUpdate()->findOrFail($id);

            if ($certificado->anulado_en !== null) {
                throw new ReglaNegocioException(
                    "El certificado {$certificado->folio} ya fue anulado."
                );
            }

            $this->sirha7->retirar($certificado);

            $certificado->update([
                'anulado_en'       => now(),
                'anulado_por'      => $anuladoPor,
                'motivo_anulacion' => $motivo,
            ]);

            return $certificado->load([
                'consultaMedica', 'emisor', 'anulador', 'diagnosticoCie10',
            ]);
        });
    }

    /**
     * El PDF del certificado, para imprimirlo o entregarlo.
     *
     * Sin esto el certificado solo existía como fila y no cumplía su función,
     * que es ser un papel.
     *
     * @return array{content: string, filename: string}
     */
    public function generarPdf(int $id): array
    {
        $certificado = CertificadoMedico::with([
            'consultaMedica.historiaClinica.servidor',
            'consultaMedica.historiaClinica.cargaFamiliar.servidor',
            'emisor.servidor', 'anulador.servidor',
            'diagnosticoCie10',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.dispensario.certificado-medico', [
            'certificado' => $certificado,
            'paciente'    => $this->datosDelPaciente($certificado),
        ])->setPaper('a4');

        return [
            'content'  => $pdf->output(),
            'filename' => 'certificado-' .
                ($certificado->folio ?? $certificado->id) . '.pdf',
        ];
    }

    /**
     * Nombre, cédula y condición del paciente, venga de servidor o de familiar.
     *
     * @return array{nombre: string, cedula: ?string, condicion: string}
     */
    private function datosDelPaciente(CertificadoMedico $certificado): array
    {
        $historia = $certificado->consultaMedica?->historiaClinica;
        $servidor = $historia?->servidor;
        $familiar = $historia?->cargaFamiliar;

        if ($servidor) {
            return [
                'nombre'    => trim("{$servidor->nombre} {$servidor->apellido}"),
                'cedula'    => $servidor->cedula,
                'condicion' => 'Servidor de la institución',
            ];
        }

        if ($familiar) {
            $titular = $familiar->servidor;

            return [
                'nombre'    => trim("{$familiar->nombres} {$familiar->apellidos}"),
                'cedula'    => $familiar->cedula,
                'condicion' => 'Carga familiar'
                    . ($titular
                        ? " de {$titular->nombre} {$titular->apellido}"
                        : ''),
            ];
        }

        return [
            'nombre'    => $historia?->nombre_paciente ?? '—',
            'cedula'    => $historia?->cedula_paciente,
            'condicion' => '—',
        ];
    }

    /**
     * Siguiente folio del año, tomado del MÁXIMO ya emitido.
     *
     * Contaba filas, y aquí eso falla de tres maneras: la tabla borra en blando
     * y el folio es único, así que un certificado retirado hacía repetir uno
     * vivo; el conteo incluía los certificados de servidores, cuyo folio lo
     * ponía su permiso y no lleva este prefijo (hasta el 2026-10-08); y
     * arrancaba en 00000 por no sumar uno. Desde entonces todos los
     * certificados llevan CERT-.
     *
     * El bloqueo de aviso serializa leer el máximo y escribir el folio entre
     * emisiones simultáneas, y lo suelta el cierre de la transacción.
     */
    private function generarFolioCertificado(): string
    {
        $anio = date('Y');

        DB::select('SELECT pg_advisory_xact_lock(?)', [
            crc32("certificado_medico_folio_{$anio}"),
        ]);

        $ultimoFolio = CertificadoMedico::withTrashed()
            ->where('folio', 'like', "CERT-{$anio}-%")
            ->max('folio');

        $ultimoSecuencial = $ultimoFolio
            ? (int) substr($ultimoFolio, strlen("CERT-{$anio}-"))
            : 0;

        $secuencial = str_pad(
            (string) ($ultimoSecuencial + 1), 5, '0', STR_PAD_LEFT
        );

        return "CERT-{$anio}-{$secuencial}";
    }
}
