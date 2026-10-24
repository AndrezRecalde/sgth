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
use Illuminate\Support\Str;
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

                // Lo que hay que contrastar con las alergias es el fármaco,
                // venga de la ficha del inventario o escrito a mano: la alergia
                // del paciente no distingue de dónde salió. Del catálogo cuenta
                // también el principio activo, porque el nombre suele ser el
                // comercial: con solo el nombre, «Dolex» no avisaba a quien es
                // alérgico al paracetamol.
                if ($esExterno) {
                    $nombres = [(string) $item['medicamento_externo']];
                } else {
                    $ficha = InventarioMedicina::findOrFail(
                        $item['inventario_medicina_id']
                    );
                    $nombres = [$ficha->nombre, (string) $ficha->principio_activo];
                }

                $todosExternos = $todosExternos && $esExterno;

                // Validar alergias (informativo)
                foreach ($alergiasMedicamento as $alergia) {
                    if ($this->coincideConAlergia($nombres, (string) $alergia->descripcion)) {
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
     * Si alguno de los nombres del fármaco coincide con la alergia.
     *
     * En los dos sentidos —«amoxicilina» contiene «amoxi», y «penicilina G
     * benzatínica» contiene «penicilina»—, sin distinguir mayúsculas ni
     * tildes: «Ácido acetilsalicílico» en la alergia y «acido
     * acetilsalicilico» en la ficha son lo mismo. Una alergia sin descripción
     * no coincide con nada; antes, la cadena vacía coincidía con todo.
     *
     * @param  list<string>  $nombres
     */
    private function coincideConAlergia(array $nombres, string $alergia): bool
    {
        $normalizar = fn (string $texto) => trim(Str::lower(Str::ascii($texto)));

        $alergia = $normalizar($alergia);

        if ($alergia === '') {
            return false;
        }

        foreach ($nombres as $nombre) {
            $nombre = $normalizar($nombre);

            if ($nombre !== '' && (str_contains($nombre, $alergia) || str_contains($alergia, $nombre))) {
                return true;
            }
        }

        return false;
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

            // Cerrada con faltantes también terminó: lo que faltaba quedó
            // «adquirir fuera», y anularla volvería inválido ese impreso.
            if ($receta->estado === EstadoReceta::CERRADA_CON_FALTANTES->value) {
                throw new ReglaNegocioException(
                    'No se puede anular una receta cerrada: lo que no se entregó ya figura como «adquirir fuera».'
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
            // La receta se bloquea antes de leer lo que falta por entregar.
            // Sin el bloqueo, dos despachos a la vez —dos personas, o dos
            // pestañas— leían los dos «10 recetadas, 0 entregadas», pasaban los
            // dos el control y sacaban 20 unidades del estante para 10
            // recetadas; el ítem decía 10 y el kardex dos egresos. Y un anular
            // simultáneo quedaba pisado por el estado que escribe el despacho.
            // Con el bloqueo el segundo espera, y al entrar ve lo que dejó el
            // primero. Los ítems se cargan después, ya bajo el bloqueo.
            $receta = RecetaMedica::lockForUpdate()->findOrFail($recetaId);
            $receta->load('items');

            if (in_array($receta->estado, [
                EstadoReceta::DESPACHADA_COMPLETA->value,
                EstadoReceta::ANULADA->value,
                EstadoReceta::EXTERNA->value,
                EstadoReceta::CERRADA_CON_FALTANTES->value,
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

                // Lo marcado como no entregado ya no se espera en el mostrador.
                if ($item->estado === ItemReceta::NO_ENTREGADO) {
                    throw new ReglaNegocioException(
                        "«{$item->inventario?->nombre}» se marcó como no entregado: el paciente lo adquiere fuera."
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

            $receta->update([
                'estado' => $this->estadoSegunItems($receta->load('items')),
                'despachado_por' => $despachadoPor,
                'despachado_en' => now(),
            ]);

            return $receta;
        });
    }

    /**
     * El mostrador no va a entregar un medicamento del inventario: agotado,
     * vencido u otro motivo (decisión del Dispensario del 2026-10-24, opción
     * A). Queda «no entregado» con quién y cuándo, y la receta se cierra si ya
     * no espera nada más. Lo entregado antes, si lo hubo, se queda; no se
     * toca el kardex, porque no sale nada del estante. No se avisa al médico.
     */
    public function marcarNoEntregado(int $recetaId, int $itemId, string $motivo, ?string $detalle, int $marcadoPor): RecetaMedica
    {
        return DB::transaction(function () use ($recetaId, $itemId, $motivo, $detalle, $marcadoPor) {
            // Con la receta bloqueada, como el despacho: los dos cambian sus ítems.
            $receta = RecetaMedica::lockForUpdate()->findOrFail($recetaId);

            if (! in_array($receta->estado, [EstadoReceta::PENDIENTE->value, EstadoReceta::DESPACHADA_PARCIAL->value], true)) {
                throw new ReglaNegocioException("La receta {$receta->folio} ya no espera entregas: no se marca nada más.");
            }

            $item = $receta->items()->with('inventario:id,nombre')->findOrFail($itemId);

            if ($item->esExterno()) {
                throw new ReglaNegocioException("«{$item->medicamento_externo}» no lo maneja la farmacia: ya se adquiere fuera.");
            }

            $nombre = $item->inventario?->nombre ?? 'El medicamento';
            if ($item->estado === 'despachado_completo') {
                throw new ReglaNegocioException("«{$nombre}» ya se entregó completo.");
            }
            if ($item->estado === ItemReceta::NO_ENTREGADO) {
                throw new ReglaNegocioException("«{$nombre}» ya está marcado como no entregado.");
            }

            $detalle = trim((string) $detalle);
            $item->update([
                'estado'               => ItemReceta::NO_ENTREGADO,
                'motivo_no_entregado'  => $motivo,
                'detalle_no_entregado' => $detalle !== '' ? $detalle : null,
                'no_entregado_por'     => $marcadoPor,
                'no_entregado_en'      => now(),
            ]);

            $receta->update(['estado' => $this->estadoSegunItems($receta->load('items'))]);

            return $receta;
        });
    }

    /**
     * El estado de la receta según sus ítems del inventario.
     *
     * Los externos no cuentan: la farmacia ya hizo con ellos todo lo que podía
     * hacer, que es nada. Sin excluirlos, una receta con un solo medicamento
     * externo no llegaría jamás a «despachada completa» por muy entregado que
     * estuviera el resto. Un «no entregado» también termina el ítem: con todo
     * entregado o marcado, la receta se cierra con faltantes.
     */
    private function estadoSegunItems(RecetaMedica $receta): string
    {
        $delCatalogo = $receta->items->reject->esExterno();
        $terminado = fn (ItemReceta $i) => in_array($i->estado, ['despachado_completo', ItemReceta::NO_ENTREGADO], true);

        return match (true) {
            $delCatalogo->every(fn (ItemReceta $i) => $i->estado === 'despachado_completo') => EstadoReceta::DESPACHADA_COMPLETA->value,
            $delCatalogo->every($terminado)                                                 => EstadoReceta::CERRADA_CON_FALTANTES->value,
            $delCatalogo->every(fn (ItemReceta $i) => $i->estado === 'pendiente')          => EstadoReceta::PENDIENTE->value,
            default                                                                         => EstadoReceta::DESPACHADA_PARCIAL->value,
        };
    }
}
