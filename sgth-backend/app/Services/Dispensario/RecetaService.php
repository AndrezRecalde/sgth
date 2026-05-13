<?php
namespace App\Services\Dispensario;

use App\Contracts\Dispensario\RecetaServiceInterface;
use App\Models\Dispensario\RecetaMedica;
use App\Models\Dispensario\ItemReceta;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\MovimientoInventarioMed;
use Illuminate\Support\Facades\DB;
use Exception;

final class RecetaService implements RecetaServiceInterface
{
    public function emitirReceta(array $datosReceta, array $items): RecetaMedica
    {
        return DB::transaction(function () use ($datosReceta, $items) {
            $receta = RecetaMedica::create($datosReceta);

            foreach ($items as $item) {
                $medicina = InventarioMedicina::lockForUpdate()->findOrFail($item['inventario_medicina_id']);

                // Validar que exista stock suficiente
                if ($medicina->stock_actual < $item['cantidad_prescrita']) {
                    throw new Exception("Stock insuficiente para el medicamento: {$medicina->nombre}. Stock actual: {$medicina->stock_actual}");
                }

                // Generar Item
                ItemReceta::create(array_merge($item, ['receta_medica_id' => $receta->id]));

                // Descontar inventario
                $medicina->stock_actual -= $item['cantidad_prescrita'];
                $medicina->save();

                // Registrar en Kardex Inmutable
                MovimientoInventarioMed::create([
                    'inventario_medicina_id' => $medicina->id,
                    'tipo_movimiento'        => 'egreso',
                    'cantidad'               => -$item['cantidad_prescrita'],
                    'stock_resultante'       => $medicina->stock_actual,
                    'motivo'                 => 'Despacho automático de receta electrónica',
                    'referencia_receta_id'   => $receta->id,
                    'registrado_por'         => auth()->id(),
                ]);
            }

            return $receta;
        });
    }
}
