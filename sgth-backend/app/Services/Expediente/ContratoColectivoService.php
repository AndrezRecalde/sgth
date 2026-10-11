<?php

namespace App\Services\Expediente;

use App\Exceptions\ReglaNegocioException;
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
        return ContratoColectivo::orderByDesc('vigencia_desde')->orderByDesc('id')->get();
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
