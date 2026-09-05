<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\AdquisicionServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\AdquisicionMedicamento;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\ItemAdquisicion;
use App\Models\Dispensario\LoteMedicina;
use App\Models\Dispensario\MovimientoInventarioMed;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class AdquisicionService implements AdquisicionServiceInterface
{
    public function __construct(
        private readonly StockPorLotes $stock
    ) {}

    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = AdquisicionMedicamento::with([
            'registrador', 'anulador', 'items.medicina',
        ])->orderBy('created_at', 'desc');

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
            'registrador', 'anulador', 'items.medicina',
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
                $itemAdquisicion = ItemAdquisicion::create([
                    'adquisicion_id'         => $adquisicion->id,
                    'inventario_medicina_id' => $item['inventario_medicina_id'],
                    'cantidad'               => $item['cantidad'],
                    'lote'                   => $item['lote'] ?? null,
                    'fecha_caducidad'        => $item['fecha_caducidad'] ?? null,
                    'precio_unitario'        => $item['precio_unitario'] ?? null,
                ]);

                $medicina = InventarioMedicina::lockForUpdate()
                    ->findOrFail($item['inventario_medicina_id']);

                // Cada entrada abre su propio lote, con su caducidad. Antes se
                // sumaba al montón y se sobrescribían el lote y la fecha de la
                // ficha, así que dos entradas con caducidades distintas se
                // volvían indistinguibles.
                $lote = $this->stock->ingresar(
                    medicina:          $medicina,
                    cantidad:          $item['cantidad'],
                    codigoLote:        $item['lote'] ?? null,
                    fechaCaducidad:    $item['fecha_caducidad'] ?? null,
                    itemAdquisicionId: $itemAdquisicion->id,
                );

                // La ficha sigue llevando lote y caducidad de la última
                // entrada: es lo que todavía leen las alertas y el bloqueo de
                // despacho. Deja de escribirse cuando esas lecturas pasen a
                // mirar los lotes, en la segunda entrega.
                if (!empty($item['lote'])) {
                    $medicina->lote = $item['lote'];
                }
                if (!empty($item['fecha_caducidad'])) {
                    $medicina->fecha_caducidad = $item['fecha_caducidad'];
                }

                $medicina->save();

                MovimientoInventarioMed::create([
                    'inventario_medicina_id' => $medicina->id,
                    'lote_id'                => $lote->id,
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

    /**
     * Anula una adquisición mal registrada devolviendo al kardex lo que
     * aportó.
     *
     * El kardex es inmutable, así que anular no borra el ingreso: escribe su
     * contrapartida. Y solo procede mientras las existencias sigan íntegras;
     * si ya se despachó parte de lo que entró, revertir la cantidad completa
     * afirmaría que volvió al estante algo que físicamente ya salió. Ese caso
     * se corrige por «Ajustar inventario», que pide motivo.
     */
    public function anular(
        int $id,
        string $motivo,
        int $anuladoPor
    ): AdquisicionMedicamento {
        return DB::transaction(function () use ($id, $motivo, $anuladoPor) {
            $adquisicion = AdquisicionMedicamento::with('items.medicina')
                ->findOrFail($id);

            if ($adquisicion->anulado_en !== null) {
                throw new ReglaNegocioException(
                    "La adquisición {$adquisicion->folio} ya fue anulada."
                );
            }

            // Se comprueban todos los ítems antes de tocar ninguno, para que
            // el rechazo nombre lo que falta en vez de morir a medio camino.
            $lotes = [];

            foreach ($adquisicion->items as $item) {
                $medicina = InventarioMedicina::lockForUpdate()
                    ->findOrFail($item->inventario_medicina_id);

                // Se mira el lote que abrió esta entrada, no el stock total de
                // la medicina: con el total, otra entrada posterior podía tapar
                // que de este lote ya había salido producto.
                $lote = LoteMedicina::where('item_adquisicion_id', $item->id)
                    ->lockForUpdate()
                    ->first();

                $quedan = $lote?->stock_actual ?? $medicina->stock_actual;

                if ($quedan < $item->cantidad) {
                    throw new ReglaNegocioException(
                        "No se puede anular: de {$medicina->nombre} entraron " .
                        "{$item->cantidad} unidades y hoy quedan {$quedan}. " .
                        'Corrija por ajuste de inventario.'
                    );
                }

                $lotes[$item->id] = [$medicina, $lote];
            }

            $motivoKardex = "Anulación de {$adquisicion->folio} — {$motivo}";

            foreach ($adquisicion->items as $item) {
                [$medicina, $lote] = $lotes[$item->id];

                if ($lote) {
                    $this->stock->revertirIngreso($lote, $item->cantidad);
                    $medicina->refresh();
                } else {
                    // Adquisiciones anteriores al control por lotes: no hay
                    // lote que devolver, solo el stock agregado.
                    $medicina->stock_actual -= $item->cantidad;
                    $medicina->save();
                }

                MovimientoInventarioMed::create([
                    'inventario_medicina_id' => $medicina->id,
                    'lote_id'                => $lote?->id,
                    'tipo_movimiento'        => 'anulacion',
                    'cantidad'               => -$item->cantidad,
                    'stock_resultante'       => $medicina->stock_actual,
                    'motivo'                 => $motivoKardex,
                    'registrado_por'         => $anuladoPor,
                ]);
            }

            $adquisicion->update([
                'anulado_en'       => now(),
                'anulado_por'      => $anuladoPor,
                'motivo_anulacion' => $motivo,
            ]);

            return $adquisicion->load([
                'items.medicina', 'registrador', 'anulador',
            ]);
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

    /**
     * Siguiente folio del año.
     *
     * Se deriva del MÁXIMO ya emitido y no de cuántas filas hay: contar
     * retrocede en cuanto una adquisición se borra, y el folio siguiente
     * chocaría con uno vivo. Se miran también las borradas por la misma razón.
     *
     * El bloqueo de aviso serializa la sección crítica entre peticiones
     * simultáneas —leer el máximo y escribir el nuevo folio— y lo libera solo
     * el cierre de la transacción. Sin él, dos registros a la vez leen el mismo
     * máximo y uno muere contra el índice único.
     */
    private function generarFolio(): string
    {
        $anio = now()->year;

        DB::select('SELECT pg_advisory_xact_lock(?)', [
            crc32("adquisicion_folio_{$anio}"),
        ]);

        $ultimoFolio = AdquisicionMedicamento::withTrashed()
            ->where('folio', 'like', "ADQ-{$anio}-%")
            ->max('folio');

        $ultimoSecuencial = $ultimoFolio
            ? (int) substr($ultimoFolio, strlen("ADQ-{$anio}-"))
            : 0;

        $secuencial = str_pad(
            (string) ($ultimoSecuencial + 1), 5, '0', STR_PAD_LEFT
        );
        return "ADQ-{$anio}-{$secuencial}";
    }
}
