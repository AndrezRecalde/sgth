<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\AdquisicionServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\AdquisicionMedicamento;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\ItemAdquisicion;
use App\Models\Dispensario\MovimientoInventarioMed;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class AdquisicionService implements AdquisicionServiceInterface
{
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = AdquisicionMedicamento::with([
            'registrador', 'items.medicina',
        ])->orderBy('fecha_adquisicion', 'desc');

        if (!empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (!empty($filtros['search'])) {
            $termino = $filtros['search'];
            $query->where(function ($q) use ($termino) {
                $q->where('numero_documento', 'ilike', "%{$termino}%")
                  ->orWhere('proveedor_o_donante', 'ilike', "%{$termino}%")
                  ->orWhere('folio', 'ilike', "%{$termino}%");
            });
        }

        return $query->paginate($filtros['per_page'] ?? 20);
    }

    public function obtener(int $id): AdquisicionMedicamento
    {
        return AdquisicionMedicamento::with([
            'registrador', 'items.medicina',
        ])->findOrFail($id);
    }

    public function registrar(
        array $datos,
        array $items,
        int $registradoPor
    ): AdquisicionMedicamento {
        if (empty($items)) {
            throw new ReglaNegocioException(
                'Debe agregar al menos un medicamento a la adquisición.'
            );
        }

        return DB::transaction(function () use (
            $datos, $items, $registradoPor
        ) {
            $folio = $this->generarFolio();

            $adquisicion = AdquisicionMedicamento::create([
                ...$datos,
                'folio'           => $folio,
                'registrado_por'  => $registradoPor,
            ]);

            $tipoLabel = $datos['tipo'] === 'donacion'
                ? 'Donación' : 'Compra';
            $motivo = "{$tipoLabel} {$folio} — Doc: " .
                "{$datos['numero_documento']} — " .
                "{$datos['proveedor_o_donante']}";

            foreach ($items as $item) {
                ItemAdquisicion::create([
                    'adquisicion_id'         => $adquisicion->id,
                    'inventario_medicina_id' => $item['inventario_medicina_id'],
                    'cantidad'               => $item['cantidad'],
                    'lote'                   => $item['lote'] ?? null,
                    'fecha_caducidad'        => $item['fecha_caducidad'] ?? null,
                    'precio_unitario'        => $item['precio_unitario'] ?? null,
                ]);

                $medicina = InventarioMedicina::lockForUpdate()
                    ->findOrFail($item['inventario_medicina_id']);

                $medicina->stock_actual += $item['cantidad'];

                if (!empty($item['lote'])) {
                    $medicina->lote = $item['lote'];
                }
                if (!empty($item['fecha_caducidad'])) {
                    $medicina->fecha_caducidad = $item['fecha_caducidad'];
                }

                $medicina->save();

                MovimientoInventarioMed::create([
                    'inventario_medicina_id' => $medicina->id,
                    'tipo_movimiento'        => 'ingreso',
                    'cantidad'               => $item['cantidad'],
                    'stock_resultante'       => $medicina->stock_actual,
                    'motivo'                 => $motivo,
                    'registrado_por'         => $registradoPor,
                ]);
            }

            return $adquisicion->load(['items.medicina', 'registrador']);
        });
    }

    public function subirDocumento(
        int $id,
        string $rutaArchivo
    ): AdquisicionMedicamento {
        $adquisicion = AdquisicionMedicamento::findOrFail($id);
        $adquisicion->update(['documento_respaldo' => $rutaArchivo]);
        return $adquisicion;
    }

    private function generarFolio(): string
    {
        $anio = now()->year;
        $cantidadActual = AdquisicionMedicamento::whereYear(
            'created_at', $anio
        )->count();
        $secuencial = str_pad(
            $cantidadActual + 1, 5, '0', STR_PAD_LEFT
        );
        return "ADQ-{$anio}-{$secuencial}";
    }
}
