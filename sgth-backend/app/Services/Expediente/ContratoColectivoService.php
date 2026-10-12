<?php

namespace App\Services\Expediente;

use App\Enums\CausaLicencia;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\ClausulaDeLicencia;
use App\Models\Expediente\ContratoColectivo;
use App\Models\Expediente\SalarioBasico;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * El contrato colectivo de los obreros y el salario básico (diseño de Acciones
 * de Personal, 9.15; fase 4.6).
 *
 * Sus cláusulas cambian cálculos que hasta aquí hacía la ley sola: los días
 * adicionales de vacaciones, las bonificaciones por jubilación y por retiro
 * voluntario —dentro de los topes del Mandato 2— y el incremento pactado, que
 * el incremento en lote propone. Solo se aplica el que rige y tiene el dictamen
 * del Ministerio de Finanzas: sin él, el contrato se tiene por inexistente
 * (Acuerdo MDT-2024-080, reformado por el MDT-2025-056).
 */
class ContratoColectivoService
{
    /** El que rige en esa fecha con el dictamen de Finanzas, si lo hay. */
    public function vigente(CarbonInterface|string|null $fecha = null): ?ContratoColectivo
    {
        return ContratoColectivo::vigentesEn($fecha ?? now())
            ->whereNotNull('dictamen_finanzas_numero')
            ->orderByDesc('vigencia_desde')
            ->orderByDesc('id')
            ->first();
    }

    /** Los días que el contrato vigente suma a los del Art. 69. */
    public function diasAdicionalesDeVacaciones(CarbonInterface|string|null $fecha = null): int
    {
        return (int) ($this->vigente($fecha)?->dias_adicionales_vacaciones ?? 0);
    }

    /** @return Collection<int, ContratoColectivo> */
    public function listar(): Collection
    {
        return ContratoColectivo::with('clausulasDeLicencia')->orderByDesc('vigencia_desde')->orderByDesc('id')->get();
    }

    /** @return Collection<int, SalarioBasico> */
    public function salariosBasicos(): Collection
    {
        return SalarioBasico::orderByDesc('anio')->get();
    }

    /**
     * Registra o corrige un contrato. Dos contratos no rigen a la vez: el
     * nuevo empieza cuando termina el anterior.
     *
     * @param  array<string, mixed>  $datos
     */
    public function guardar(array $datos, ?UploadedFile $archivo, ?ContratoColectivo $contrato, int $userId): ContratoColectivo
    {
        $this->validarQueNoSeCruce($datos, $contrato);

        return DB::transaction(function () use ($datos, $archivo, $contrato, $userId) {
            $contrato ??= new ContratoColectivo(['created_by' => $userId]);
            $anterior = $contrato->documento_ruta;

            $contrato->fill([
                'numero'                        => trim((string) $datos['numero']),
                'fecha_suscripcion'             => $datos['fecha_suscripcion'] ?? null,
                'fecha_registro_mdt'            => $datos['fecha_registro_mdt'] ?? null,
                'vigencia_desde'                => $datos['vigencia_desde'],
                'vigencia_hasta'                => $datos['vigencia_hasta'] ?? null,
                'dictamen_finanzas_numero'      => filled($datos['dictamen_finanzas_numero'] ?? null)
                    ? trim((string) $datos['dictamen_finanzas_numero'])
                    : null,
                'dictamen_finanzas_fecha'       => filled($datos['dictamen_finanzas_numero'] ?? null)
                    ? ($datos['dictamen_finanzas_fecha'] ?? null)
                    : null,
                // Sin la cláusula, la ley sola: cero días, sin bonificación.
                'dias_adicionales_vacaciones'   => (int) ($datos['dias_adicionales_vacaciones'] ?? 0),
                'bonificacion_jubilacion_sbu'   => $this->decimalONulo($datos['bonificacion_jubilacion_sbu'] ?? null),
                'bonificacion_retiro_sbu'       => $this->decimalONulo($datos['bonificacion_retiro_sbu'] ?? null),
                'incremento_pactado_porcentaje' => $this->decimalONulo($datos['incremento_pactado_porcentaje'] ?? null),
                'observacion'                   => filled($datos['observacion'] ?? null) ? trim((string) $datos['observacion']) : null,
                'updated_by'                    => $userId,
            ]);

            if ($archivo) {
                $ruta = $archivo->store('expediente/contratos-colectivos', 'local');

                if (! $ruta) {
                    throw new ReglaNegocioException('No se pudo guardar el archivo. Inténtelo de nuevo.');
                }

                $contrato->documento_ruta = $ruta;
                $contrato->documento_nombre = $archivo->getClientOriginalName();
            }

            $contrato->save();

            // El anterior se borra después de guardar el nuevo.
            if ($archivo && $anterior && $anterior !== $contrato->documento_ruta) {
                Storage::disk('local')->delete($anterior);
            }

            return $contrato->fresh();
        });
    }

    /**
     * Registra o corrige una cláusula de licencia del contrato (fase 5.3).
     *
     * La que amplía una causa de la ley tiene que dar más días que la ley: los
     * derechos del trabajador son irrenunciables (Código del Trabajo, Art. 4),
     * y una cláusula que da menos no se aplicaría. Una causa se amplía una vez
     * por contrato y caso. La causa nueva lleva su nombre.
     *
     * @param  array{clausula: string, causa?: ?string, alcance?: ?string, nombre?: ?string, dias: int|string, dias_por_anio?: int|string|null, observacion?: ?string}  $datos
     */
    public function guardarClausulaDeLicencia(
        ContratoColectivo $contrato, array $datos, ?ClausulaDeLicencia $clausula, int $userId,
    ): ClausulaDeLicencia {
        $this->impedirSiYaRespaldaLicencias($clausula);

        $causa = filled($datos['causa'] ?? null) ? CausaLicencia::from($datos['causa']) : null;
        $alcance = $causa && filled($datos['alcance'] ?? null) ? (string) $datos['alcance'] : null;
        $dias = (int) $datos['dias'];

        if ($causa) {
            $this->validarAmpliacion($contrato, $causa, $alcance, $dias, $clausula);
        }

        $clausula ??= new ClausulaDeLicencia(['contrato_colectivo_id' => $contrato->id, 'created_by' => $userId]);

        $clausula->fill([
            'clausula'      => trim((string) $datos['clausula']),
            'causa'         => $causa?->value,
            'alcance'       => $alcance,
            'nombre'        => $causa ? null : trim((string) ($datos['nombre'] ?? '')),
            'dias'          => $dias,
            'dias_por_anio' => filled($datos['dias_por_anio'] ?? null) ? (int) $datos['dias_por_anio'] : null,
            'observacion'   => filled($datos['observacion'] ?? null) ? trim((string) $datos['observacion']) : null,
            'updated_by'    => $userId,
        ])->save();

        return $clausula->fresh('contratoColectivo');
    }

    public function eliminarClausulaDeLicencia(ClausulaDeLicencia $clausula): void
    {
        $this->impedirSiYaRespaldaLicencias($clausula);

        $clausula->delete();
    }

    /**
     * La licencia dice lo que se le concedió con la cláusula: cambiarla o
     * borrarla después le cambiaría el respaldo a una licencia ya registrada.
     */
    private function impedirSiYaRespaldaLicencias(?ClausulaDeLicencia $clausula): void
    {
        if ($clausula?->licencias()->exists()) {
            throw new ReglaNegocioException(
                "La {$clausula->clausula} ya respalda licencias registradas: no se cambia ni se borra. "
                    .'Si el contrato colectivo cambió, regístrelo como un contrato nuevo.'
            );
        }
    }

    private function validarAmpliacion(
        ContratoColectivo $contrato, CausaLicencia $causa, ?string $alcance, int $dias, ?ClausulaDeLicencia $clausula,
    ): void {
        if ($alcance === null && $causa->exigeAlcance()) {
            throw new ReglaNegocioException(
                "La licencia por {$causa->etiqueta()} distingue de quién es: indique el caso que la cláusula amplía."
            );
        }

        if ($alcance !== null && ! array_key_exists($alcance, $causa->alcances())) {
            throw new ReglaNegocioException("Ese caso no corresponde a la licencia por {$causa->etiqueta()}.");
        }

        $legal = $causa->tope($alcance);

        if ($legal === null) {
            throw new ReglaNegocioException(
                "La licencia por {$causa->etiqueta()} no tiene tope en la ley: dura lo que diga el certificado, y no hay días que ampliar."
            );
        }

        if ($dias <= $legal) {
            throw new ReglaNegocioException(
                "La ley ya da {$legal} días por {$causa->etiqueta()} ({$causa->baseLegal()}): una cláusula que da {$dias} no la "
                    .'mejora, y los derechos del trabajador son irrenunciables (Código del Trabajo, Art. 4).'
            );
        }

        $otra = ClausulaDeLicencia::where('contrato_colectivo_id', $contrato->id)
            ->where('causa', $causa->value)
            ->when($alcance, fn ($q) => $q->where('alcance', $alcance), fn ($q) => $q->whereNull('alcance'))
            ->when($clausula?->id, fn ($q) => $q->where('id', '!=', $clausula->id))
            ->first();

        if ($otra) {
            throw new ReglaNegocioException(
                "El contrato ya amplía la licencia por {$otra->etiqueta()} en la {$otra->clausula}: corrija esa."
            );
        }
    }

    /**
     * El salario básico de un año: lo registra o lo corrige.
     *
     * @param  array{anio: int|string, monto: float|string, acuerdo?: ?string}  $datos
     */
    public function guardarSalarioBasico(array $datos, int $userId): SalarioBasico
    {
        $salario = SalarioBasico::firstOrNew(['anio' => (int) $datos['anio']]);
        $salario->created_by ??= $userId;

        $salario->fill([
            'monto'      => $datos['monto'],
            'acuerdo'    => filled($datos['acuerdo'] ?? null) ? trim((string) $datos['acuerdo']) : null,
            'updated_by' => $userId,
        ])->save();

        return $salario->fresh();
    }

    /** @param  array<string, mixed>  $datos */
    private function validarQueNoSeCruce(array $datos, ?ContratoColectivo $contrato): void
    {
        $desde = Carbon::parse($datos['vigencia_desde'])->toDateString();
        $hasta = filled($datos['vigencia_hasta'] ?? null) ? Carbon::parse($datos['vigencia_hasta'])->toDateString() : null;

        $otro = ContratoColectivo::query()
            ->when($contrato?->id, fn ($q) => $q->where('id', '!=', $contrato->id))
            ->where(fn ($q) => $q->whereNull('vigencia_hasta')->orWhereDate('vigencia_hasta', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('vigencia_desde', '<=', $hasta))
            ->orderBy('vigencia_desde')
            ->first();

        if ($otro) {
            throw new ReglaNegocioException(
                "Su vigencia se cruza con la del contrato colectivo N.º {$otro->numero} (desde el "
                    .$otro->vigencia_desde->format('d/m/Y')
                    .($otro->vigencia_hasta ? ' hasta el '.$otro->vigencia_hasta->format('d/m/Y') : ', sin fecha de fin')
                    .'): ciérrelo antes, o ajuste las fechas.'
            );
        }
    }

    private function decimalONulo(mixed $valor): ?float
    {
        return $valor === null || $valor === '' ? null : (float) $valor;
    }
}
