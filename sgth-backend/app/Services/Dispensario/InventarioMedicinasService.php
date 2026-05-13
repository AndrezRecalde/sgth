<?php
namespace App\Services\Dispensario;

use App\Contracts\Dispensario\InventarioMedicinasServiceInterface;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\MovimientoInventarioMed;
use Illuminate\Support\Facades\DB;

final class InventarioMedicinasService implements InventarioMedicinasServiceInterface
{
    public function ingresarMedicina(array $datos): InventarioMedicina
    {
        return DB::transaction(function () use ($datos) {
            $medicina = InventarioMedicina::create($datos);

            if ($medicina->stock_actual > 0) {
                MovimientoInventarioMed::create([
                    'inventario_medicina_id' => $medicina->id,
                    'tipo_movimiento'        => 'ingreso',
                    'cantidad'               => $medicina->stock_actual,
                    'stock_resultante'       => $medicina->stock_actual,
                    'motivo'                 => 'Ingreso inicial al inventario',
                    'registrado_por'         => auth()->id(),
                ]);
            }

            return $medicina;
        });
    }
}
