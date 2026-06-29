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
        $query = InventarioMedicina::orderBy('nombre');

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

    public function buscar(string $termino): Collection
    {
        return InventarioMedicina::where('estado', true)
            ->where('stock_actual', '>', 0)
            ->where(function ($q) use ($termino) {
                $q->where('nombre', 'ilike', "%{$termino}%")
                  ->orWhere('principio_activo', 'ilike', "%{$termino}%")
                  ->orWhere('codigo', 'ilike', "%{$termino}%");
            })
            ->orderBy('nombre')
            ->limit(20)
            ->get();
    }

    public function ingresarMedicina(
        array $datos,
        int $registradoPor
    ): InventarioMedicina {
        return DB::transaction(function () use ($datos, $registradoPor) {
            $medicina = InventarioMedicina::create([
                ...$datos,
                'codigo'     => $this->generarCodigo(),
                'created_by' => $registradoPor,
            ]);

            if ($medicina->stock_actual > 0) {
                MovimientoInventarioMed::create([
                    'inventario_medicina_id' => $medicina->id,
                    'tipo_movimiento'        => 'ingreso',
                    'cantidad'               => $medicina->stock_actual,
                    'stock_resultante'       => $medicina->stock_actual,
                    'motivo'                 => 'Ingreso inicial al inventario',
                    'registrado_por'         => $registradoPor,
                ]);
            }

            return $medicina;
        });
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

    public function ingresarStock(
        int $id,
        int $cantidad,
        string $motivo,
        int $registradoPor
    ): InventarioMedicina {
        if ($cantidad <= 0) {
            throw new ReglaNegocioException(
                'La cantidad a ingresar debe ser mayor a cero.'
            );
        }

        return DB::transaction(function () use (
            $id, $cantidad, $motivo, $registradoPor
        ) {
            $medicina = InventarioMedicina::lockForUpdate()
                ->findOrFail($id);

            $medicina->stock_actual += $cantidad;
            $medicina->save();

            MovimientoInventarioMed::create([
                'inventario_medicina_id' => $medicina->id,
                'tipo_movimiento'        => 'ingreso',
                'cantidad'               => $cantidad,
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
