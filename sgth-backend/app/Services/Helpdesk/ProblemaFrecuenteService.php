<?php

namespace App\Services\Helpdesk;

use App\Exceptions\ReglaNegocioException;
use App\Models\Helpdesk\ProblemaFrecuente;
use Illuminate\Support\Facades\DB;

/**
 * El catálogo de problemas frecuentes de «Pedir ayuda a TIC» (respuestas de
 * TIC del 2026-11-05). Lo mantienen quienes tienen
 * `gestionar-problemas-frecuentes`: el jefe de TIC y los técnicos que él
 * designe. El servidor ve los activos en el orden que fija TIC.
 */
final class ProblemaFrecuenteService
{
    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        return ProblemaFrecuente::with('categoria:id,nombre,activa,deleted_at,unidad_tic_id', 'categoria.unidadTic:id,nombre')
            ->orderByDesc('activo')->orderBy('orden')->orderBy('id')
            ->get()
            ->map(fn (ProblemaFrecuente $p) => $this->fila($p))
            ->all();
    }

    /** @param  array<string, mixed>  $datos */
    public function crear(array $datos): ProblemaFrecuente
    {
        // Al final de la lista; TIC lo sube si hace falta.
        $datos['orden'] = (int) ProblemaFrecuente::max('orden') + 1;
        $datos['activo'] ??= true;

        return ProblemaFrecuente::create($datos);
    }

    /** @param  array<string, mixed>  $datos */
    public function actualizar(ProblemaFrecuente $problema, array $datos): ProblemaFrecuente
    {
        $problema->update($datos);

        return $problema;
    }

    /**
     * El orden en que el servidor ve los problemas: la lista completa de ids,
     * de arriba abajo. Una lista que no es la del catálogo se rechaza, para
     * que dos personas ordenando a la vez no dejen órdenes repetidos.
     *
     * @param  list<int>  $ids
     */
    public function ordenar(array $ids): void
    {
        $actuales = ProblemaFrecuente::pluck('id')->sort()->values()->all();
        $recibidos = collect($ids)->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($actuales !== $recibidos) {
            throw new ReglaNegocioException('La lista cambió mientras la ordenaba: vuelva a cargarla e inténtelo de nuevo.');
        }

        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $posicion => $id) {
                ProblemaFrecuente::whereKey($id)->update(['orden' => $posicion + 1]);
            }
        });
    }

    /** @return array<string, mixed> */
    public function fila(ProblemaFrecuente $p): array
    {
        $p->loadMissing('categoria.unidadTic:id,nombre');
        $categoria = $p->categoria;

        return [
            'id'               => $p->id,
            'nombre'           => $p->nombre,
            'descripcion'      => $p->descripcion,
            'categoria_id'     => $p->categoria_id,
            'categoria'        => $categoria?->nombre,
            // Con la categoría inactiva, el problema deja de ofrecerse aunque
            // esté activo: la pantalla lo avisa.
            'categoria_activa' => (bool) ($categoria?->activa && ! $categoria->trashed()),
            'unidad_tic'       => $categoria?->unidadTic?->nombre,
            'tipo_ticket'      => $p->tipo_ticket,
            'prioridad'        => $p->prioridad->value,
            'orden'            => $p->orden,
            'activo'           => $p->activo,
        ];
    }
}
