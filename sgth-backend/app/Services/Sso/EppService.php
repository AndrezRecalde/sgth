<?php

namespace App\Services\Sso;

use App\Enums\EstadoKitEpp;
use App\Enums\MotivoEntregaEpp;
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

    /**
     * Agrega un equipo al EPP que requiere un puesto.
     *
     * Rechaza el duplicado en vez de sobrescribirlo. Era un `updateOrCreate`:
     * volver a elegir un equipo que el puesto ya requería no daba error, pisaba
     * `cantidad_requerida` y dejaba la frecuencia de reposición en NULL —el
     * segundo argumento la escribe con `?? null`, y el formulario la manda
     * vacía si no se rellena—, todo respondiendo «equipo asignado».
     *
     * De este requerimiento sale el kit que se entrega al servidor, así que
     * perder la frecuencia es perder cuándo toca reponer. Para cambiar una
     * asignación existente está el borrado, que es una decisión deliberada.
     */
    public function asignarEquipoAPuesto(array $datos): PuestoEpp
    {
        $yaRequerido = PuestoEpp::query()
            ->where('puesto_id', $datos['puesto_id'])
            ->where('equipo_proteccion_id', $datos['equipo_proteccion_id'])
            ->exists();

        if ($yaRequerido) {
            throw ValidationException::withMessages([
                'equipo_proteccion_id' => 'Este puesto ya requiere ese equipo. Elimine la asignación existente para cambiar su cantidad o su frecuencia de reposición.',
            ]);
        }

        return PuestoEpp::create([
            'puesto_id' => $datos['puesto_id'],
            'equipo_proteccion_id' => $datos['equipo_proteccion_id'],
            'cantidad_requerida' => $datos['cantidad_requerida'] ?? 1,
            'frecuencia_reposicion_meses' => $datos['frecuencia_reposicion_meses'] ?? null,
        ]);
    }

    /**
     * La asignación se busca DENTRO del puesto de la URL.
     *
     * Antes se borraba por id a secas, así que
     * `DELETE /sso/puestos/7/equipos-proteccion/{id}` borraba igual una
     * asignación del puesto 3: la URL afirmaba una relación que nadie
     * comprobaba. Todos los que llegan aquí tienen `gestionar-sso`, así que no
     * era una escalada de privilegios, pero sí un borrado que la auditoría no
     * podía explicar —y, con una pantalla desincronizada, uno que nadie pidió.
     */
    public function eliminarAsignacion(int $puestoId, int $id): void
    {
        PuestoEpp::where('puesto_id', $puestoId)->findOrFail($id)->delete();
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
     * Kit de EPP requerido para el puesto del servidor, con lo que ya se le
     * entregó. Vacío si el servidor no tiene puesto asignado.
     *
     * Antes devolvía el requerimiento del puesto a secas, y el modal
     * «Entregar kit completo» lo premarcaba entero, siempre. No sabía nada de
     * lo ya entregado: entregar el mismo kit dos veces creaba filas duplicadas
     * en `epp_entregas` sin un aviso. La comprobación manual que quedó
     * pendiente en `docs/pendientes-sso.md` —«los equipos entregados ya no
     * deben aparecer pendientes»— no estaba implementada; invalidar la caché
     * del kit, que es lo que se hizo entonces, no podía arreglarlo, porque
     * este endpoint no tenía noción de «pendiente».
     *
     * Cada fila llega con tres campos calculados: `ultima_entrega`,
     * `estado_kit` y `reponer_desde`. El plazo sale de
     * `frecuencia_reposicion_meses` del puesto y, si no la fijó, de la
     * `vida_util_meses` del equipo — dos columnas que existían y no leía
     * nadie. La decisión vive en `EstadoKitEpp`, que se prueba sin base de
     * datos.
     *
     * Devoluciones aparte: una devolución no es una entrega, así que no cuenta
     * para el plazo. Se miran `entrega` y `reposicion`.
     */
    public function listarKitParaServidor(int $servidorId): Collection
    {
        $servidor = Servidor::findOrFail($servidorId);

        if (! $servidor->puesto_id) {
            return new Collection();
        }

        $requeridos = $this->listarEquiposPorPuesto($servidor->puesto_id);

        if ($requeridos->isEmpty()) {
            return $requeridos;
        }

        // Una consulta para todo el kit, no una por equipo: el modal lo abre
        // quien está entregando y cada fila serían dos viajes a la base.
        $ultimasEntregas = EppEntrega::query()
            ->where('servidor_id', $servidorId)
            ->whereIn('equipo_proteccion_id', $requeridos->pluck('equipo_proteccion_id'))
            ->whereIn('motivo', [
                MotivoEntregaEpp::ENTREGA->value,
                MotivoEntregaEpp::REPOSICION->value,
            ])
            ->groupBy('equipo_proteccion_id')
            ->selectRaw('equipo_proteccion_id, MAX(fecha_entrega) AS ultima')
            ->pluck('ultima', 'equipo_proteccion_id');

        return $requeridos->each(function (PuestoEpp $requerido) use ($ultimasEntregas) {
            $ultima = $ultimasEntregas->get($requerido->equipo_proteccion_id);
            $ultima = $ultima !== null ? Carbon::parse($ultima) : null;

            $frecuencia = $requerido->frecuencia_reposicion_meses;
            $vidaUtil = $requerido->equipoProteccion?->vida_util_meses;

            $requerido->setAttribute('ultima_entrega', $ultima?->toDateString());
            $requerido->setAttribute(
                'estado_kit',
                EstadoKitEpp::desde($ultima, $frecuencia, $vidaUtil)->value,
            );
            $requerido->setAttribute(
                'reponer_desde',
                EstadoKitEpp::reponerDesde($ultima, $frecuencia, $vidaUtil)?->toDateString(),
            );
        });
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
