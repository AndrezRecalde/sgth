<?php

namespace App\Services\InventarioTi;

use App\Contracts\InventarioTi\BienInformaticoServiceInterface;
use App\Enums\EstadoBien;
use App\Exceptions\ReglaNegocioException;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\InventarioTi\OrigenBien;
use App\Models\InventarioTi\SubtipoBien;
use App\Models\InventarioTi\TipoBien;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La ficha de un equipo (docs/diseno-subsistema-tic.md, 4.2; fase 1.1).
 *
 * Salió de `InventarioTiService`, que seguía con la custodia, el acta, el
 * historial y la baja: esas partes se rehacen en sus fases (1.3, 1.5, 1.6).
 *
 * Reglas que viven aquí y no en la policy, para que ningún atajo de rol las
 * salte:
 * - el estado `en_uso`/`disponible` sale de la custodia y la baja tiene su
 *   propio trámite;
 * - un componente cuelga de un equipo principal, y solo un nivel;
 * - un equipo prestable no tiene custodio: está en TIC.
 */
final class BienInformaticoService implements BienInformaticoServiceInterface
{
    /** Lo que el listado y la ficha muestran de cada equipo. */
    private const RELACIONES = [
        'tipo:id,nombre,anios_vida_util', 'subtipo:id,tipo_bien_id,nombre', 'marca:id,nombre',
        'ubicacion:id,sede,piso', 'unidadAdministrativa:id,nombre,acronimo',
        'contratoVigencia:id,proceso,fecha_fin',
        'asignacionVigente.servidor:id,nombre,apellido,cedula,unidad_administrativa_id',
        'asignacionVigente.servidor.unidadAdministrativa:id,nombre,acronimo',
    ];

    public function listar(array $filtros): LengthAwarePaginator
    {
        $porPagina = min(max((int) ($filtros['per_page'] ?? 20), 1), 100);

        return $this->consulta($filtros)->with(self::RELACIONES)->withCount('componentes')->paginate($porPagina);
    }

    public function paraEtiquetas(array $filtros, int $maximo): Collection
    {
        $query = $this->consulta($filtros);
        if (! empty($filtros['ids'])) {
            $ids = is_array($filtros['ids']) ? $filtros['ids'] : explode(',', (string) $filtros['ids']);
            $query->whereKey(array_map('intval', $ids));
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            throw new ReglaNegocioException('Ningún equipo coincide con el filtro: no hay etiquetas que imprimir.');
        }
        if ($total > $maximo) {
            throw new ReglaNegocioException("El filtro deja {$total} equipos y se imprimen hasta {$maximo} etiquetas de una vez: acote la búsqueda.");
        }

        return $query->with(['tipo:id,nombre', 'marca:id,nombre'])->get();
    }

    /** El filtro del listado, que también eligen las etiquetas en lote. */
    private function consulta(array $filtros): Builder
    {
        $query = BienInformatico::query()
            ->orderBy('codigo_institucional')
            ->orderBy('id');

        if (! empty($filtros['search'])) {
            $termino = '%' . trim((string) $filtros['search']) . '%';

            // Lo que se lee en la etiqueta, en la placa del fabricante o en la
            // pantalla del equipo, y el nombre de quien lo tiene.
            $query->where(function (Builder $q) use ($termino) {
                foreach (['codigo_institucional', 'codigo_anterior', 'numero_serie', 'modelo', 'nombre_red', 'usuario_dominio'] as $columna) {
                    $q->orWhere($columna, 'ilike', $termino);
                }
                // El número de soporte del sistema antiguo (D1), en la custodia
                // o en un mantenimiento: con él se cruza un memorando viejo.
                $q->orWhereHas('asignaciones', fn (Builder $a) => $a->where('soporte_anterior', 'ilike', $termino))
                    ->orWhereHas('mantenimientos', fn (Builder $m) => $m->where('soporte_anterior', 'ilike', $termino));
                $q->orWhereHas('asignacionVigente.servidor', fn (Builder $s) => $s
                    ->where('nombre', 'ilike', $termino)
                    ->orWhere('apellido', 'ilike', $termino)
                    ->orWhere('cedula', 'ilike', $termino)
                    ->orWhereRaw("concat(apellido, ' ', nombre) ilike ?", [$termino]));
            });
        }

        foreach (['tipo_bien_id', 'subtipo_bien_id', 'marca_id', 'ubicacion_id', 'contrato_vigencia_id', 'estado_operativo', 'condicion_fisica'] as $campo) {
            if (! empty($filtros[$campo])) {
                $query->where($campo, $filtros[$campo]);
            }
        }

        if (isset($filtros['prestable']) && $filtros['prestable'] !== '') {
            $query->where('prestable', filter_var($filtros['prestable'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filtros['custodio_id'])) {
            $query->whereHas('asignacionVigente', fn (Builder $q) => $q->where('servidor_id', $filtros['custodio_id']));
        }

        if (! empty($filtros['sin_custodio'])) {
            $query->whereDoesntHave('asignacionVigente');
        }

        // La unidad de un equipo es la de quien lo tiene; la del propio equipo
        // solo vale mientras nadie lo tenga.
        if (! empty($filtros['unidad_administrativa_id'])) {
            $unidad = (int) $filtros['unidad_administrativa_id'];
            $query->where(fn (Builder $q) => $q
                ->whereHas('asignacionVigente.servidor', fn (Builder $s) => $s->where('unidad_administrativa_id', $unidad))
                ->orWhere(fn (Builder $sin) => $sin
                    ->whereDoesntHave('asignacionVigente')
                    ->where('unidad_administrativa_id', $unidad)));
        }

        if (! empty($filtros['solo_principales'])) {
            $query->whereNull('bien_padre_id');
        }

        return $query;
    }

    public function obtener(int $id): BienInformatico
    {
        return BienInformatico::with([
            ...self::RELACIONES,
            'origen',
            'padre:id,codigo_institucional,modelo,tipo_bien_id',
            'padre.tipo:id,nombre',
            'componentes:id,bien_padre_id,codigo_institucional,modelo,tipo_bien_id,estado_operativo',
            'componentes.tipo:id,nombre',
            'bajaVigente',
        ])->withCount('componentes')->findOrFail($id);
    }

    public function registrar(array $datos): BienInformatico
    {
        return DB::transaction(function () use ($datos) {
            $this->validarSubtipo($datos['tipo_bien_id'], $datos['subtipo_bien_id'] ?? null);
            $this->validarPadre(null, $datos['bien_padre_id'] ?? null);

            $estado = isset($datos['estado_operativo'])
                ? EstadoBien::from($datos['estado_operativo'])
                : EstadoBien::DISPONIBLE;

            $bien = BienInformatico::create([
                ...$datos,
                'estado_operativo'    => $estado->esDeCustodia() ? EstadoBien::DISPONIBLE : $estado,
                'codigo_qr'           => $this->codigoQr($datos['codigo_institucional']),
                'fecha_fin_vida_util' => $this->finDeVidaUtil($datos),
                'created_by'          => auth()->id(),
            ]);

            return $this->obtener($bien->id);
        });
    }

    public function actualizar(int $id, array $datos): BienInformatico
    {
        return DB::transaction(function () use ($id, $datos) {
            $bien = BienInformatico::lockForUpdate()->findOrFail($id);

            $tipo = $datos['tipo_bien_id'] ?? $bien->tipo_bien_id;
            $subtipo = array_key_exists('subtipo_bien_id', $datos) ? $datos['subtipo_bien_id'] : $bien->subtipo_bien_id;
            // Si cambia el tipo, el subtipo anterior deja de servir.
            if (isset($datos['tipo_bien_id']) && ! array_key_exists('subtipo_bien_id', $datos)
                && (int) $datos['tipo_bien_id'] !== (int) $bien->tipo_bien_id) {
                $subtipo = $datos['subtipo_bien_id'] = null;
            }
            $this->validarSubtipo($tipo, $subtipo);

            if (array_key_exists('bien_padre_id', $datos)) {
                $this->validarPadre($bien, $datos['bien_padre_id']);
            }

            if (array_key_exists('estado_operativo', $datos)) {
                $datos['estado_operativo'] = $this->nuevoEstado($bien, EstadoBien::from($datos['estado_operativo']));
            }

            if (! empty($datos['prestable']) && ! $bien->prestable) {
                $this->validarPrestable($bien);
            }

            if (isset($datos['codigo_institucional']) && $datos['codigo_institucional'] !== $bien->codigo_institucional) {
                $datos['codigo_qr'] = $this->codigoQr($datos['codigo_institucional']);
            }

            $datos['fecha_fin_vida_util'] = $this->finDeVidaUtil([
                'tipo_bien_id'   => $tipo,
                'fecha_compra'   => array_key_exists('fecha_compra', $datos) ? $datos['fecha_compra'] : $bien->fecha_compra,
                'origen_bien_id' => array_key_exists('origen_bien_id', $datos) ? $datos['origen_bien_id'] : $bien->origen_bien_id,
            ]);
            $datos['updated_by'] = auth()->id();

            $bien->update($datos);

            return $this->obtener($bien->id);
        });
    }

    /**
     * Borrar la ficha de un equipo registrado por error.
     *
     * Solo mientras no tenga historia: un equipo que ya se entregó, pasó por
     * mantenimiento o tiene componentes no es un error de digitación. Si salió
     * de servicio, va por «bajas».
     */
    public function retirar(int $id): void
    {
        DB::transaction(function () use ($id) {
            $bien = BienInformatico::lockForUpdate()->findOrFail($id);

            $asignaciones = AsignacionBien::where('bien_informatico_id', $bien->id)->count();
            $mantenimientos = MantenimientoBien::where('bien_informatico_id', $bien->id)->count();
            $componentes = $bien->componentes()->count();
            // Una baja, aunque se rechazara, es historia del equipo.
            $bajas = $bien->bajas()->count();

            if ($asignaciones > 0 || $mantenimientos > 0 || $componentes > 0 || $bajas > 0) {
                throw new ReglaNegocioException(
                    "El equipo «{$bien->codigo_institucional}» tiene historial "
                    . "({$asignaciones} asignación(es), {$mantenimientos} mantenimiento(s), {$componentes} componente(s) y {$bajas} baja(s)): "
                    . 'no puede borrarse del inventario. Si salió de servicio, regístrelo como baja.'
                );
            }

            $bien->delete();
        });
    }

    /**
     * El estado que pide una edición. `en_uso` y `disponible` no se eligen:
     * pedir cualquiera de los dos es «vuelve al servicio», y el sistema pone el
     * que corresponde a la custodia.
     */
    private function nuevoEstado(BienInformatico $bien, EstadoBien $pedido): EstadoBien
    {
        if ($bien->estado_operativo === EstadoBien::DADO_DE_BAJA) {
            throw new ReglaNegocioException('El equipo está dado de baja: su estado ya no cambia.');
        }

        if ($pedido === EstadoBien::DADO_DE_BAJA) {
            throw new ReglaNegocioException(
                'La baja de un equipo se registra en «bajas», con su informe técnico: no editando su estado.'
            );
        }

        return $pedido->esDeCustodia() ? $bien->estadoSegunCustodia() : $pedido;
    }

    private function validarSubtipo(int|string|null $tipoId, int|string|null $subtipoId): void
    {
        if ($subtipoId === null) {
            return;
        }

        if (! SubtipoBien::whereKey($subtipoId)->where('tipo_bien_id', $tipoId)->exists()) {
            throw new ReglaNegocioException('El subtipo elegido no corresponde al tipo de equipo.');
        }
    }

    /** Un componente cuelga de un equipo principal, y solo un nivel. */
    private function validarPadre(?BienInformatico $bien, int|string|null $padreId): void
    {
        if ($padreId === null) {
            return;
        }

        if ($bien && (int) $padreId === (int) $bien->id) {
            throw new ReglaNegocioException('Un equipo no puede ser componente de sí mismo.');
        }

        $padre = BienInformatico::findOrFail($padreId);

        if ($padre->bien_padre_id !== null) {
            throw new ReglaNegocioException(
                "«{$padre->codigo_institucional}» ya es componente de otro equipo: elija el equipo principal."
            );
        }

        if ($bien && $bien->componentes()->exists()) {
            throw new ReglaNegocioException(
                "«{$bien->codigo_institucional}» tiene componentes propios: no puede ser componente de otro equipo."
            );
        }
    }

    /** El grupo de préstamo está en TIC: un equipo con custodio no se presta. */
    private function validarPrestable(BienInformatico $bien): void
    {
        if ($bien->estado_operativo === EstadoBien::DADO_DE_BAJA) {
            throw new ReglaNegocioException('Un equipo dado de baja no se presta.');
        }

        if ($bien->asignacionVigente()->exists()) {
            throw new ReglaNegocioException(
                'El equipo tiene un custodio: para prestarlo, primero debe devolverse a TIC.'
            );
        }
    }

    private function codigoQr(string $codigo): string
    {
        return $codigo . '-QR';
    }

    /**
     * El fin de la vida útil: la compra (o la adquisición del origen, si no
     * hay fecha de compra) más los años del tipo. Null si falta cualquiera.
     *
     * @param  array<string, mixed>  $datos
     */
    private function finDeVidaUtil(array $datos): ?Carbon
    {
        $tipo = empty($datos['tipo_bien_id']) ? null : TipoBien::find($datos['tipo_bien_id']);

        if (! $tipo || $tipo->anios_vida_util <= 0) {
            return null;
        }

        $desde = $datos['fecha_compra'] ?? null;

        if (empty($desde) && ! empty($datos['origen_bien_id'])) {
            $desde = OrigenBien::find($datos['origen_bien_id'])?->fecha_adquisicion;
        }

        return empty($desde) ? null : Carbon::parse($desde)->addYears($tipo->anios_vida_util);
    }
}
