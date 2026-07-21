<?php

namespace App\Services\Sso;

use App\Models\Sso\PuestoEpp;
use App\Models\Sso\EppEntrega;
use App\Models\Expediente\Servidor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EppService
{
    // ── EPP requerido por puesto ────────────────────────────────────

    public function listarEquiposPorPuesto(int $puestoId): Collection
    {
        return PuestoEpp::with('equipoProteccion')
            ->where('puesto_id', $puestoId)
            ->get();
    }

    public function asignarEquipoAPuesto(array $datos): PuestoEpp
    {
        return PuestoEpp::updateOrCreate(
            [
                'puesto_id' => $datos['puesto_id'],
                'equipo_proteccion_id' => $datos['equipo_proteccion_id'],
            ],
            [
                'cantidad_requerida' => $datos['cantidad_requerida'] ?? 1,
                'frecuencia_reposicion_meses' => $datos['frecuencia_reposicion_meses'] ?? null,
            ]
        );
    }

    public function eliminarAsignacion(int $id): void
    {
        PuestoEpp::findOrFail($id)->delete();
    }

    // ── Entregas de EPP ──────────────────────────────────────────────

    public function listarEntregas(array $filtros): LengthAwarePaginator
    {
        return EppEntrega::query()
            ->with(['servidor', 'equipoProteccion', 'entregador'])
            ->when(isset($filtros['servidor_id']), fn($q) => $q->where('servidor_id', $filtros['servidor_id']))
            ->when(isset($filtros['equipo_proteccion_id']), fn($q) => $q->where('equipo_proteccion_id', $filtros['equipo_proteccion_id']))
            ->when(isset($filtros['fecha_inicio']), fn($q) => $q->whereDate('fecha_entrega', '>=', $filtros['fecha_inicio']))
            ->when(isset($filtros['fecha_fin']), fn($q) => $q->whereDate('fecha_entrega', '<=', $filtros['fecha_fin']))
            ->orderByDesc('fecha_entrega')
            ->paginate($filtros['por_pagina'] ?? 15);
    }

    public function registrarEntrega(array $datos): EppEntrega
    {
        $datos['entregado_por'] = auth()->id();
        return EppEntrega::create($datos);
    }

    /**
     * Kit de EPP requerido para el puesto del servidor (para precargar el modo
     * "entregar kit completo" en el frontend). Vacío si el servidor no tiene puesto asignado.
     */
    public function listarKitParaServidor(int $servidorId): Collection
    {
        $servidor = Servidor::findOrFail($servidorId);

        if (! $servidor->puesto_id) {
            return new Collection();
        }

        return $this->listarEquiposPorPuesto($servidor->puesto_id);
    }

    /**
     * Registra varias entregas de EPP para un mismo servidor en una sola operación
     * (kit completo), una fila por equipo en una transacción — todo o nada.
     *
     * @param array{servidor_id:int, fecha_entrega:string, observaciones:?string, equipos:array<int,array{equipo_proteccion_id:int, cantidad:?int}>} $datos
     * @return \Illuminate\Support\Collection<int, EppEntrega>
     */
    public function registrarEntregaKit(array $datos): \Illuminate\Support\Collection
    {
        if (empty($datos['equipos'])) {
            throw ValidationException::withMessages([
                'equipos' => 'Debe seleccionar al menos un equipo del kit.',
            ]);
        }

        return DB::transaction(function () use ($datos) {
            $entregadoPor = auth()->id();

            return collect($datos['equipos'])->map(fn (array $equipo) => EppEntrega::create([
                'servidor_id' => $datos['servidor_id'],
                'equipo_proteccion_id' => $equipo['equipo_proteccion_id'],
                'fecha_entrega' => $datos['fecha_entrega'],
                'cantidad' => $equipo['cantidad'] ?? 1,
                'motivo' => 'entrega',
                'entregado_por' => $entregadoPor,
                'observaciones' => $datos['observaciones'] ?? null,
            ]));
        });
    }

    /**
     * Lista de EPP entregados: agregación por servidor en un período, opcionalmente filtrada por puesto.
     */
    public function reporteEntregas(array $filtros): array
    {
        $fechaInicio = Carbon::parse($filtros['fecha_inicio'])->startOfDay();
        $fechaFin = Carbon::parse($filtros['fecha_fin'])->endOfDay();

        $entregas = EppEntrega::with(['servidor.puesto.cargo', 'equipoProteccion'])
            ->whereBetween('fecha_entrega', [$fechaInicio, $fechaFin])
            ->when(
                isset($filtros['puesto_id']),
                fn($q) => $q->whereHas('servidor', fn($sq) => $sq->where('puesto_id', $filtros['puesto_id']))
            )
            ->get();

        $consolidado = $entregas
            ->groupBy('servidor_id')
            ->map(function ($items) {
                $servidor = $items->first()->servidor;

                return [
                    'servidor_id' => $servidor?->id,
                    'servidor_nombre' => trim(implode(' ', array_filter([
                        $servidor?->nombre, $servidor?->apellido,
                    ]))),
                    'puesto' => $servidor?->puesto?->cargo?->nombre ?? '—',
                    'total_entregas' => $items->where('motivo', 'entrega')->count(),
                    'total_devoluciones' => $items->where('motivo', 'devolucion')->count(),
                    'total_reposiciones' => $items->where('motivo', 'reposicion')->count(),
                    'equipos' => $items->map(fn($i) => [
                        'equipo' => $i->equipoProteccion?->nombre,
                        'fecha' => $i->fecha_entrega->format('Y-m-d'),
                        'motivo' => $i->motivo->value,
                        'cantidad' => $i->cantidad,
                    ])->values(),
                ];
            })
            ->values();

        return [
            'consolidado' => $consolidado,
            'totales' => [
                'total_registros' => $entregas->count(),
                'total_servidores' => $consolidado->count(),
            ],
        ];
    }
}
