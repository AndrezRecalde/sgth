<?php

namespace App\Services\Expediente;

use App\Enums\EstadoContrato;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use Illuminate\Support\Collection;

/**
 * Ausencias temporales: quién no está y desde cuándo, y si el hueco ya está
 * cubierto.
 *
 * Talento Humano necesita esto para contratar personal de apoyo mientras el
 * titular está en comisión de servicios o con licencia sin remuneración. La
 * ausencia no es un estado guardado — se deriva del período de la acción de
 * personal, de modo que al vencer desaparece sola del listado sin que ninguna
 * tarea tenga que apagarla.
 */
class AusenciaTemporalService
{
    /**
     * @param  array{fecha?: ?string, cubiertas?: ?bool, unidad_id?: ?int}  $filtros
     * @return list<array<string, mixed>>
     */
    public function listar(array $filtros = []): array
    {
        $fecha = $filtros['fecha'] ?? now()->toDateString();

        $ausencias = MovimientoPersonal::query()
            ->esAusenciaTemporal()
            ->ausenciaVigenteEn($fecha)
            ->with([
                'servidor:id,nombre,apellido,cedula',
                'unidadOrigen:id,nombre',
                'puestoOrigen.cargo:id,nombre',
                'unidadDestino:id,nombre',
            ])
            ->orderBy('fecha_fin')
            ->get();

        $reemplazos = $this->reemplazosDe($ausencias->pluck('id')->all());

        $filas = $ausencias->map(function (MovimientoPersonal $ausencia) use ($reemplazos) {
            $reemplazo = $reemplazos->get($ausencia->id);

            return [
                'id'               => $ausencia->id,
                'codigo_registro'  => $ausencia->codigo_registro,
                'tipo_movimiento'  => $ausencia->tipo_movimiento?->value,
                'subtipo_movimiento' => $ausencia->subtipo_movimiento?->value,
                'etiqueta'         => $ausencia->etiquetaAusencia(),
                'desde'            => $ausencia->fecha_inicio?->toDateString(),
                'hasta'            => $ausencia->fecha_fin?->toDateString(),
                'dias_restantes'   => $this->diasRestantes($ausencia),
                'servidor'         => [
                    'id'       => $ausencia->servidor?->id,
                    'nombre'   => trim(($ausencia->servidor?->apellido ?? '').' '.($ausencia->servidor?->nombre ?? '')),
                    'cedula'   => $ausencia->servidor?->cedula,
                ],
                // Los ids acompañan a los nombres para que el formulario de
                // reemplazo pueda preseleccionar el puesto del ausente: el
                // suplente entra a esa misma plaza, no a otra.
                'unidad'           => $ausencia->unidadOrigen?->nombre,
                'unidad_id'        => $ausencia->unidad_origen_id,
                'puesto'           => $ausencia->puestoOrigen?->cargo?->nombre,
                'puesto_id'        => $ausencia->puesto_origen_id,
                'destino'          => $ausencia->unidadDestino?->nombre,
                'reemplazo'        => $reemplazo,
            ];
        });

        if (array_key_exists('cubiertas', $filtros) && $filtros['cubiertas'] !== null) {
            $filas = $filas->filter(
                fn (array $f) => ((bool) $f['reemplazo']) === (bool) $filtros['cubiertas']
            );
        }

        return $filas->values()->all();
    }

    /**
     * Reemplazo vigente por ausencia. Solo se consideran los contratos ya
     * materializados: un ingreso en borrador todavía puede anularse, y
     * mostrarlo como cobertura le haría creer a Talento Humano que el hueco
     * está resuelto cuando aún no lo está.
     *
     * @param  list<int>  $ausenciaIds
     * @return Collection<int, array<string, mixed>>
     */
    private function reemplazosDe(array $ausenciaIds): Collection
    {
        if ($ausenciaIds === []) {
            return collect();
        }

        return ContratoServidor::whereIn('cubre_movimiento_id', $ausenciaIds)
            ->where('estado', EstadoContrato::VIGENTE->value)
            ->with('servidor:id,nombre,apellido,cedula')
            ->get()
            ->keyBy('cubre_movimiento_id')
            ->map(fn (ContratoServidor $c) => [
                'contrato_id'       => $c->id,
                'numero_contrato'   => $c->numero_contrato,
                'tipo_nombramiento' => $c->tipo_nombramiento?->value,
                'desde'             => $c->fecha_inicio?->toDateString(),
                'hasta'             => $c->fecha_fin?->toDateString(),
                'servidor'          => [
                    'id'     => $c->servidor?->id,
                    'nombre' => trim(($c->servidor?->apellido ?? '').' '.($c->servidor?->nombre ?? '')),
                    'cedula' => $c->servidor?->cedula,
                ],
            ]);
    }

    /** Null cuando la ausencia no tiene fecha de fin pactada. */
    private function diasRestantes(MovimientoPersonal $ausencia): ?int
    {
        if (! $ausencia->fecha_fin) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($ausencia->fecha_fin, false);
    }
}
