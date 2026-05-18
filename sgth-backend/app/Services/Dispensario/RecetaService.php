<?php
namespace App\Services\Dispensario;

use App\Contracts\Dispensario\RecetaServiceInterface;
use App\Models\Dispensario\RecetaMedica;
use App\Models\Dispensario\ItemReceta;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\MovimientoInventarioMed;
use App\Models\Dispensario\ConsultaMedica;
use Illuminate\Support\Facades\DB;
use Exception;

final class RecetaService implements RecetaServiceInterface
{
    public function emitirReceta(array $datosReceta, array $items): array
    {
        return DB::transaction(function () use ($datosReceta, $items) {
            // La receta inicia como pendiente
            $datosReceta['estado'] = 'pendiente';
            $receta = RecetaMedica::create($datosReceta);
            $alertasAlergias = [];

            // Obtener alergias del paciente tipo medicamento
            $consulta = ConsultaMedica::with('historiaClinica.alergias')->find($datosReceta['consulta_medica_id'] ?? null);
            $alergiasMedicamento = $consulta ? $consulta->historiaClinica->alergias()->where('tipo', 'medicamento')->get() : collect();

            foreach ($items as $item) {
                $medicina = InventarioMedicina::findOrFail($item['inventario_medicina_id']);

                // Validar alergias (informativo)
                foreach ($alergiasMedicamento as $alergia) {
                    if (stripos($medicina->nombre, $alergia->descripcion) !== false || stripos($alergia->descripcion, $medicina->nombre) !== false) {
                        $alertasAlergias[] = "Advertencia: El paciente tiene alergia registrada a {$alergia->descripcion} con severidad {$alergia->severidad}";
                    }
                }

                // Generar Item (se registra la prescripción sin afectar stock ni kardex)
                ItemReceta::create(array_merge($item, [
                    'receta_medica_id' => $receta->id,
                    'cantidad_despachada' => 0,
                    'estado' => 'pendiente'
                ]));
            }

            return [
                'receta' => $receta,
                'alertas_alergias' => array_values(array_unique($alertasAlergias))
            ];
        });
    }

    public function despacharReceta(int $recetaId, array $itemsDespachados, int $despachadoPor): RecetaMedica
    {
        return DB::transaction(function () use ($recetaId, $itemsDespachados, $despachadoPor) {
            $receta = RecetaMedica::with('items')->findOrFail($recetaId);

            if (in_array($receta->estado, ['despachada_completa', 'anulada'])) {
                throw new Exception("La receta no puede ser despachada porque su estado es: {$receta->estado}");
            }

            foreach ($itemsDespachados as $despacho) {
                $itemRecetaId = $despacho['item_receta_id'] ?? null;
                $cantidadADespachar = $despacho['cantidad'] ?? 0;

                if ($cantidadADespachar <= 0) {
                    continue; // Ignorar cantidades cero o negativas
                }

                $item = $receta->items->where('id', $itemRecetaId)->first();
                if (!$item) {
                    throw new Exception("El ítem de receta {$itemRecetaId} no pertenece a esta receta.");
                }

                // No se puede despachar más de lo prescrito
                $cantidadFaltante = $item->cantidad_prescrita - $item->cantidad_despachada;
                if ($cantidadADespachar > $cantidadFaltante) {
                    throw new Exception("No se puede despachar más de lo prescrito para el ítem {$itemRecetaId}. Falta por despachar: {$cantidadFaltante}");
                }

                $medicina = InventarioMedicina::lockForUpdate()->findOrFail($item->inventario_medicina_id);

                // Validar que exista stock suficiente para el despacho físico
                if ($medicina->stock_actual < $cantidadADespachar) {
                    throw new Exception("Stock insuficiente para el medicamento: {$medicina->nombre}. Stock actual: {$medicina->stock_actual}");
                }

                // Actualizar el estado del ítem
                $nuevaCantidadDespachada = $item->cantidad_despachada + $cantidadADespachar;
                $nuevoEstadoItem = ($nuevaCantidadDespachada == $item->cantidad_prescrita) ? 'despachado_completo' : 'despachado_parcial';

                $item->update([
                    'cantidad_despachada' => $nuevaCantidadDespachada,
                    'estado' => $nuevoEstadoItem
                ]);

                // Descontar inventario
                $medicina->stock_actual -= $cantidadADespachar;
                $medicina->save();

                // Registrar en Kardex Inmutable
                MovimientoInventarioMed::create([
                    'inventario_medicina_id' => $medicina->id,
                    'tipo_movimiento'        => 'egreso',
                    'cantidad'               => -$cantidadADespachar,
                    'stock_resultante'       => $medicina->stock_actual,
                    'motivo'                 => 'Despacho de receta electrónica',
                    'referencia_receta_id'   => $receta->id,
                    'registrado_por'         => $despachadoPor,
                ]);
            }

            // Evaluar estado general de la receta
            $receta->load('items'); // Recargar para estado actualizado
            
            $todosCompletos = $receta->items->every(fn($i) => $i->estado === 'despachado_completo');
            $todosPendientes = $receta->items->every(fn($i) => $i->estado === 'pendiente');

            if ($todosCompletos) {
                $estadoReceta = 'despachada_completa';
            } elseif ($todosPendientes) {
                $estadoReceta = 'pendiente';
            } else {
                $estadoReceta = 'despachada_parcial';
            }

            $receta->update([
                'estado' => $estadoReceta,
                'despachado_por' => $despachadoPor,
                'despachado_en' => now(),
            ]);

            return $receta;
        });
    }
}
