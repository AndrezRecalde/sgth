<?php

namespace App\Services\Dispensario;

use App\Enums\EstadoPermiso;
use App\Enums\TipoPermiso;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Dispensario\CertificadoMedico;
use App\Models\Dispensario\ConsultaMedica;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CertificadoMedicoService
{
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

            $permisoId = null;
            $folio     = null;

            // Solo genera permiso de asistencia si el
            // paciente es el servidor titular (no familiar)
            if ($esServidor) {
                $permiso = $this->crearPermisoAutomatico(
                    $historia->servidor_id,
                    $fechaInicio,
                    $fechaFin,
                    $emisorId,
                    $datos['observaciones'] ?? null,
                );
                $permisoId = $permiso->id;
                $folio     = $permiso->folio;
            } else {
                $folio = $this->generarFolioCertificado();
            }

            $certificado = CertificadoMedico::create([
                'consulta_medica_id'   => $consulta->id,
                'emitido_por'          => $emisorId,
                'dias_reposo'          => $dias,
                'fecha_inicio'         => $fechaInicio,
                'fecha_fin'            => $fechaFin,
                'diagnostico_cie10_id' => $datos['diagnostico_cie10_id'] ?? null,
                'observaciones'        => $datos['observaciones'] ?? null,
                'permiso_servidor_id'  => $permisoId,
                'folio'                => $folio,
                'tipo_paciente'        => $esServidor ? 'servidor' : 'beneficiario',
                'created_by'           => $emisorId,
            ]);

            return $certificado->load([
                'consultaMedica',
                'emisor',
                'diagnosticoCie10',
                'permisoServidor',
            ]);
        });
    }

    private function crearPermisoAutomatico(
        int $servidorId,
        Carbon $fecha,
        Carbon $fechaFin,
        int $emisorId,
        ?string $observacion,
    ): PermisoServidor {
        $folio = $this->generarFolioPermiso($fecha->year);

        return PermisoServidor::create([
            'servidor_id' => $servidorId,
            'tipo'        => TipoPermiso::ENFERMEDAD->value,
            'fecha'       => $fecha,
            'hora_inicio' => '00:00',
            'hora_fin'    => '23:59',
            'vence_en'    => $fechaFin->copy()->endOfDay(),
            'observacion' => $observacion
                ?? 'Certificado médico emitido por el dispensario.',
            // Activo directo: el médico es fuente
            // confiable, se salta confirmación de Recepción
            'estado'        => EstadoPermiso::ACTIVO->value,
            'folio'         => $folio,
            'confirmado_por' => $emisorId,
            'confirmado_en'  => now(),
            'creado_por'     => $emisorId,
        ]);
    }

    /**
     * El folio del permiso que acompaña al certificado, con el mismo criterio.
     *
     * Contaba filas de `permisos_servidor`, que también borra en blando y
     * también tiene el folio único: un permiso retirado hacía repetir uno vivo.
     * Y arrancaba en 00000. Se toca desde aquí porque es este servicio el que
     * lo emite; el resto de Asistencia crea sus permisos por otro camino.
     */
    private function generarFolioPermiso(int $anio): string
    {
        DB::select('SELECT pg_advisory_xact_lock(?)', [
            crc32("permiso_servidor_folio_{$anio}"),
        ]);

        $ultimoFolio = PermisoServidor::withTrashed()
            ->where('folio', 'like', "PER-{$anio}-%")
            ->max('folio');

        $ultimoSecuencial = $ultimoFolio
            ? (int) substr($ultimoFolio, strlen("PER-{$anio}-"))
            : 0;

        return "PER-{$anio}-" . str_pad(
            (string) ($ultimoSecuencial + 1), 5, '0', STR_PAD_LEFT
        );
    }

    /**
     * Siguiente folio del año, tomado del MÁXIMO ya emitido.
     *
     * Contaba filas, y aquí eso falla de tres maneras: la tabla borra en blando
     * y el folio es único, así que un certificado retirado hacía repetir uno
     * vivo; el conteo incluía los certificados de servidores, cuyo folio lo
     * pone el permiso y no lleva este prefijo; y arrancaba en 00000 por no
     * sumar uno.
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
