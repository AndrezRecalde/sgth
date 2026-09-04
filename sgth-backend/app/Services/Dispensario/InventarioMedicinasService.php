<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\InventarioMedicinasServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\MovimientoInventarioMed;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class InventarioMedicinasService implements InventarioMedicinasServiceInterface
{
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = InventarioMedicina::orderBy('created_at', 'desc');

        if (!empty($filtros['search'])) {
            $termino = $filtros['search'];
            $query->where(function ($q) use ($termino) {
                $q->where('nombre', 'ilike', "%{$termino}%")
                  ->orWhere('codigo', 'ilike', "%{$termino}%")
                  ->orWhere('principio_activo', 'ilike', "%{$termino}%");
            });
        }

        if (isset($filtros['estado'])) {
            $query->where('estado', filter_var(
                $filtros['estado'], FILTER_VALIDATE_BOOLEAN
            ));
        }

        if (!empty($filtros['stock_bajo'])) {
            $query->whereColumn('stock_actual', '<=', 'stock_minimo');
        }

        return $query->paginate($filtros['per_page'] ?? 20);
    }

    public function obtener(int $id): InventarioMedicina
    {
        return InventarioMedicina::findOrFail($id);
    }

    /**
     * Busca en el catálogo activo.
     *
     * Quien receta solo puede elegir lo que la farmacia podrá entregarle: con
     * existencias y sin caducar, porque el despacho rechaza lo vencido y de
     * nada sirve prescribir algo que se frenará en el mostrador. Quien registra
     * una adquisición necesita ver todo el catálogo, incluido lo agotado y lo
     * vencido, que es justamente lo que va a reponer.
     */
    public function buscar(
        string $termino,
        bool $soloDespachables = true
    ): Collection {
        return InventarioMedicina::where('estado', true)
            ->when($soloDespachables, fn ($q) => $q
                ->where('stock_actual', '>', 0)
                ->where(fn ($sub) => $sub
                    ->whereNull('fecha_caducidad')
                    ->orWhereDate('fecha_caducidad', '>=', now()->toDateString())))
            ->where(function ($q) use ($termino) {
                $q->where('nombre', 'ilike', "%{$termino}%")
                  ->orWhere('principio_activo', 'ilike', "%{$termino}%")
                  ->orWhere('codigo', 'ilike', "%{$termino}%");
            })
            ->orderBy('nombre')
            ->limit(20)
            ->get();
    }

    /**
     * Da de alta un medicamento en el CATÁLOGO. Define qué maneja la farmacia,
     * no cuánto tiene: nace siempre en cero y sus existencias entran después
     * por adquisición, para que todo aumento de stock tenga respaldo.
     */
    public function ingresarMedicina(
        array $datos,
        int $registradoPor
    ): InventarioMedicina {
        return InventarioMedicina::create([
            ...$datos,
            'stock_actual' => 0,
            'codigo'       => $this->generarCodigo(),
            'created_by'   => $registradoPor,
        ]);
    }

    private function generarCodigo(): string
    {
        $ultimoCodigo = InventarioMedicina::withTrashed()
            ->orderByDesc('id')
            ->value('codigo');

        $ultimoSecuencial = $ultimoCodigo
            ? (int) str_replace('MED-', '', $ultimoCodigo)
            : 0;

        $secuencial = str_pad(
            (string)($ultimoSecuencial + 1), 4, '0', STR_PAD_LEFT
        );
        return "MED-{$secuencial}";
    }

    public function actualizar(
        int $id,
        array $datos
    ): InventarioMedicina {
        $medicina = InventarioMedicina::findOrFail($id);
        $medicina->update($datos);
        return $medicina;
    }

    /**
     * Saca existencias del inventario por una causa conocida: caducidad, merma,
     * rotura, contaminación.
     *
     * Se distingue del ajuste a propósito. El ajuste dice «el conteo físico da
     * X» y no explica la diferencia; la baja dice cuántas unidades salen y por
     * qué. Sin ella, bloquear el despacho de caducados dejaría ese stock
     * atrapado sin más salida que un ajuste que no nombra la causa.
     */
    public function registrarBaja(
        int $id,
        int $cantidad,
        string $motivo,
        int $registradoPor
    ): InventarioMedicina {
        if ($cantidad <= 0) {
            throw new ReglaNegocioException(
                'La cantidad a dar de baja debe ser mayor a cero.'
            );
        }

        return DB::transaction(function () use (
            $id, $cantidad, $motivo, $registradoPor
        ) {
            $medicina = InventarioMedicina::lockForUpdate()
                ->findOrFail($id);

            if ($medicina->stock_actual < $cantidad) {
                throw new ReglaNegocioException(
                    "No se pueden dar de baja {$cantidad} unidades de " .
                    "{$medicina->nombre}: solo quedan " .
                    "{$medicina->stock_actual}."
                );
            }

            $medicina->stock_actual -= $cantidad;
            $medicina->save();

            MovimientoInventarioMed::create([
                'inventario_medicina_id' => $medicina->id,
                'tipo_movimiento'        => 'baja',
                'cantidad'               => -$cantidad,
                'stock_resultante'       => $medicina->stock_actual,
                'motivo'                 => $motivo,
                'registrado_por'         => $registradoPor,
            ]);

            return $medicina;
        });
    }

    public function ajustarInventario(
        int $id,
        int $nuevoStock,
        string $motivo,
        int $registradoPor
    ): InventarioMedicina {
        if ($nuevoStock < 0) {
            throw new ReglaNegocioException(
                'El stock ajustado no puede ser negativo.'
            );
        }

        return DB::transaction(function () use (
            $id, $nuevoStock, $motivo, $registradoPor
        ) {
            $medicina = InventarioMedicina::lockForUpdate()
                ->findOrFail($id);

            $diferencia = $nuevoStock - $medicina->stock_actual;

            if ($diferencia === 0) {
                throw new ReglaNegocioException(
                    'El nuevo stock es igual al actual, no hay ajuste que registrar.'
                );
            }

            $medicina->stock_actual = $nuevoStock;
            $medicina->save();

            MovimientoInventarioMed::create([
                'inventario_medicina_id' => $medicina->id,
                'tipo_movimiento'        => 'ajuste',
                'cantidad'               => $diferencia,
                'stock_resultante'       => $nuevoStock,
                'motivo'                 => $motivo,
                'registrado_por'         => $registradoPor,
            ]);

            return $medicina;
        });
    }

    public function darDeBaja(int $id): InventarioMedicina
    {
        $medicina = InventarioMedicina::findOrFail($id);
        $medicina->update(['estado' => !$medicina->estado]);
        return $medicina;
    }

    public function kardex(int $id): Collection
    {
        InventarioMedicina::findOrFail($id);

        return MovimientoInventarioMed::where(
            'inventario_medicina_id', $id
        )
            ->with('registrador')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
