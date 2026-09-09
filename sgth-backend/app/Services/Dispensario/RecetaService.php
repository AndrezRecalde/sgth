<?php
namespace App\Services\Dispensario;

use App\Contracts\Dispensario\RecetaServiceInterface;
use App\Enums\EstadoReceta;
use App\Models\Dispensario\RecetaMedica;
use App\Models\Dispensario\ItemReceta;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\MovimientoInventarioMed;
use App\Models\Dispensario\ConsultaMedica;
use Illuminate\Support\Facades\DB;
use App\Exceptions\ReglaNegocioException;

final class RecetaService implements RecetaServiceInterface
{
    public function __construct(
        private readonly StockPorLotes $stock
    ) {}

    public function emitirReceta(array $datosReceta, array $items): array
    {
        return DB::transaction(function () use ($datosReceta, $items) {
            // La receta inicia como pendiente
            $datosReceta['estado'] = 'pendiente';
            $datosReceta['folio']  = $this->generarFolio();
            $receta = RecetaMedica::create($datosReceta);
            $alertasAlergias = [];

            // Obtener alergias del paciente tipo medicamento
            $consulta = ConsultaMedica::with('historiaClinica.alergias')->find($datosReceta['consulta_medica_id'] ?? null);
            // Solo las vigentes: una alergia anulada es una que se descartó, y
            // seguir avisando de ella enseña a ignorar el aviso.
            $alergiasMedicamento = $consulta ? $consulta->historiaClinica->alergias()->activas()->where('tipo', 'medicamento')->get() : collect();

            $todosExternos = true;

            foreach ($items as $item) {
                // Un ítem es del catálogo o es externo; el CHECK de la tabla y
                // StoreRecetaMedicaRequest garantizan que sea exactamente uno.
                $esExterno = empty($item['inventario_medicina_id']);

                // Lo que hay que contrastar con las alergias es el nombre del
                // fármaco, venga de la ficha del inventario o escrito a mano:
                // la alergia del paciente no distingue de dónde salió.
                $nombre = $esExterno
                    ? trim((string) $item['medicamento_externo'])
                    : InventarioMedicina::findOrFail(
                        $item['inventario_medicina_id']
                    )->nombre;

                $todosExternos = $todosExternos && $esExterno;

                // Validar alergias (informativo)
                foreach ($alergiasMedicamento as $alergia) {
                    if (stripos($nombre, $alergia->descripcion) !== false || stripos($alergia->descripcion, $nombre) !== false) {
                        $alertasAlergias[] = "Advertencia: El paciente tiene alergia registrada a {$alergia->descripcion} con severidad {$alergia->severidad}";
                    }
                }

                // Generar Item (se registra la prescripción sin afectar stock ni kardex)
                ItemReceta::create(array_merge($item, [
                    'receta_medica_id' => $receta->id,
                    'cantidad_despachada' => 0,
                    // El externo nace cerrado: no hay entrega que esperar, y
                    // dejarlo «pendiente» lo tendría eternamente en la cola.
                    'estado' => $esExterno
                        ? ItemReceta::NO_DISPONIBLE
                        : 'pendiente',
                ]));
            }

            // Si no hay nada que la farmacia maneje, el mostrador no tiene nada
            // que hacer con esta receta: se cierra al emitirse en vez de
            // quedarse pendiente de una entrega que nadie puede hacer.
            if ($todosExternos) {
                $receta->update(['estado' => EstadoReceta::EXTERNA->value]);
            }

            return [
                'receta' => $receta,
                'alertas_alergias' => array_values(array_unique($alertasAlergias))
            ];
        });
    }

    /**
     * Siguiente folio del año, tomado del MÁXIMO ya emitido.
     *
     * Se lee el máximo y no se cuentan filas, por lo mismo que en el
     * certificado médico: la tabla borra en blando y el folio es único, así que
     * contar haría que una receta retirada hiciera repetir el folio de una
     * viva.
     *
     * El bloqueo de aviso serializa leer el máximo y escribir el folio entre
     * emisiones simultáneas —dos médicos recetando a la vez—, y lo suelta el
     * cierre de la transacción que ya envuelve a `emitirReceta`.
     */
    private function generarFolio(): string
    {
        $anio = date('Y');

        DB::select('SELECT pg_advisory_xact_lock(?)', [
            crc32("receta_medica_folio_{$anio}"),
        ]);

        $ultimo = RecetaMedica::withTrashed()
            ->where('folio', 'like', "REC-{$anio}-%")
            ->max('folio');

        $secuencial = $ultimo
            ? (int) substr($ultimo, strlen("REC-{$anio}-")) + 1
            : 1;

        return "REC-{$anio}-" . str_pad(
            (string) $secuencial, 5, '0', STR_PAD_LEFT
        );
    }

    /**
     * Anula una receta para que no se entregue lo que falta.
     *
     * No devuelve stock: lo ya despachado salió físicamente del estante y su
     * egreso sigue vigente en el kardex. Anular cierra la receta, y por eso
     * alcanza también a las parciales — si no, una receta a medio entregar se
     * quedaría para siempre en la cola de despacho cuando el paciente no
     * vuelve o el médico cambia el tratamiento.
     */
    public function anularReceta(
        int $recetaId,
        string $motivo,
        int $anuladoPor
    ): RecetaMedica {
        return DB::transaction(function () use ($recetaId, $motivo, $anuladoPor) {
            $receta = RecetaMedica::lockForUpdate()->findOrFail($recetaId);

            if ($receta->estado === 'anulada') {
                throw new ReglaNegocioException('La receta ya fue anulada.');
            }

            if ($receta->estado === 'despachada_completa') {
                throw new ReglaNegocioException(
                    'No se puede anular una receta ya despachada por completo.'
                );
            }

            $receta->update([
                'estado'           => 'anulada',
                'anulado_en'       => now(),
                'anulado_por'      => $anuladoPor,
                'motivo_anulacion' => $motivo,
            ]);

            return $receta;
        });
    }

    public function despacharReceta(int $recetaId, array $itemsDespachados, int $despachadoPor): RecetaMedica
    {
        return DB::transaction(function () use ($recetaId, $itemsDespachados, $despachadoPor) {
            $receta = RecetaMedica::with('items')->findOrFail($recetaId);

            if (in_array($receta->estado, [
                EstadoReceta::DESPACHADA_COMPLETA->value,
                EstadoReceta::ANULADA->value,
                EstadoReceta::EXTERNA->value,
            ])) {
                throw new ReglaNegocioException("La receta no puede ser despachada porque su estado es: {$receta->estado}");
            }

            foreach ($itemsDespachados as $despacho) {
                $itemRecetaId = $despacho['item_receta_id'] ?? null;
                $cantidadADespachar = $despacho['cantidad'] ?? 0;

                if ($cantidadADespachar <= 0) {
                    continue; // Ignorar cantidades cero o negativas
                }

                $item = $receta->items->where('id', $itemRecetaId)->first();
                if (!$item) {
                    throw new ReglaNegocioException("El ítem de receta {$itemRecetaId} no pertenece a esta receta.");
                }

                // De un medicamento que la farmacia no maneja no hay nada que
                // sacar del estante. Se rechaza aquí y no más abajo para no
                // dejar que llegue a buscar una ficha de inventario que no
                // existe: el paciente lo adquiere fuera.
                if ($item->esExterno()) {
                    throw new ReglaNegocioException(
                        "«{$item->medicamento_externo}» no lo maneja la " .
                        'farmacia: el paciente lo adquiere fuera y no se ' .
                        'despacha desde el dispensario.'
                    );
                }

                // No se puede despachar más de lo prescrito
                $cantidadFaltante = $item->cantidad_prescrita - $item->cantidad_despachada;
                if ($cantidadADespachar > $cantidadFaltante) {
                    throw new ReglaNegocioException("No se puede despachar más de lo prescrito para el ítem {$itemRecetaId}. Falta por despachar: {$cantidadFaltante}");
                }

                $medicina = InventarioMedicina::lockForUpdate()->findOrFail($item->inventario_medicina_id);

                // Un medicamento vencido no sale del estante, y ahora la
                // comprobación es exacta: la hace el propio consumo, que solo
                // toca lotes vigentes. Antes se miraba la fecha de la ficha —la
                // de la última entrada— y se erraba en las dos direcciones:
                // paraba el despacho entero teniendo existencias buenas, o
                // dejaba salir un lote viejo ya caducado.

                // Actualizar el estado del ítem
                $nuevaCantidadDespachada = $item->cantidad_despachada + $cantidadADespachar;
                $nuevoEstadoItem = ($nuevaCantidadDespachada == $item->cantidad_prescrita) ? 'despachado_completo' : 'despachado_parcial';

                $item->update([
                    'cantidad_despachada' => $nuevaCantidadDespachada,
                    'estado' => $nuevoEstadoItem
                ]);

                // Descontar inventario siguiendo FEFO: sale primero lo que
                // caduca antes, y un despacho puede repartirse entre lotes.
                $reparto = $this->stock->consumirParaDespacho(
                    $medicina, $cantidadADespachar
                );

                // Un movimiento por lote: son unidades distintas, con
                // caducidades distintas, las que salieron del estante, y el
                // kardex tiene que poder decirlo. El corrido se reconstruye
                // hacia adelante desde antes de la salida.
                $restante = $medicina->stock_actual + $cantidadADespachar;

                foreach ($reparto as $salida) {
                    $restante -= $salida['cantidad'];

                    MovimientoInventarioMed::create([
                        'inventario_medicina_id' => $medicina->id,
                        'lote_id'                => $salida['lote']->id,
                        'tipo_movimiento'        => 'egreso',
                        'cantidad'               => -$salida['cantidad'],
                        'stock_resultante'       => $restante,
                        // El lote va en su columna del kardex, no dentro del
                        // texto: repetirlo aquí sería duplicarlo en la fila.
                        'motivo'                 => 'Despacho de receta electrónica',
                        'referencia_receta_id'   => $receta->id,
                        'registrado_por'         => $despachadoPor,
                    ]);
                }
            }

            // Evaluar estado general de la receta
            $receta->load('items'); // Recargar para estado actualizado
            
            // Los externos no cuentan para nada de esto: la farmacia ya hizo
            // con ellos todo lo que podía hacer, que es nada. Sin excluirlos,
            // una receta con un solo medicamento externo no llegaría jamás a
            // «despachada completa» por muy entregado que estuviera el resto.
            $delCatalogo = $receta->items->reject->esExterno();

            $todosCompletos = $delCatalogo->every(
                fn ($i) => $i->estado === 'despachado_completo'
            );
            $todosPendientes = $delCatalogo->every(
                fn ($i) => $i->estado === 'pendiente'
            );

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
