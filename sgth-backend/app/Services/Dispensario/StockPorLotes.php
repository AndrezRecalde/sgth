<?php

namespace App\Services\Dispensario;

use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\LoteMedicina;

/**
 * El único sitio que mueve existencias.
 *
 * Todo lo que cambia el stock —entrada, anulación, despacho, baja, ajuste—
 * pasa por aquí, y aquí se mantiene el invariante que sostiene el resto:
 *
 *     suma(lotes.stock_actual) == inventario_medicinas.stock_actual
 *
 * `stock_actual` de la ficha se queda como caché de esa suma. Podría
 * derivarse, pero hay siete archivos que lo leen y convertirlos de golpe
 * habría hecho de este cambio otro mucho mayor; el invariante y sus tests son
 * lo que permite ir migrándolos sin sobresaltos.
 *
 * Cada método devuelve el reparto por lotes para que quien llame escriba el
 * kardex: un despacho que toca dos lotes son dos movimientos, porque son dos
 * cosas distintas las que salieron del estante.
 *
 * @phpstan-type Reparto list<array{lote: LoteMedicina, cantidad: int}>
 */
final class StockPorLotes
{
    /**
     * Abre un lote y suma sus unidades al stock de la medicina.
     *
     * @return LoteMedicina el lote recién abierto
     */
    public function ingresar(
        InventarioMedicina $medicina,
        int $cantidad,
        ?string $codigoLote = null,
        ?string $fechaCaducidad = null,
        ?int $itemAdquisicionId = null
    ): LoteMedicina {
        if ($cantidad <= 0) {
            throw new ReglaNegocioException(
                'La cantidad que ingresa debe ser mayor a cero.'
            );
        }

        $lote = LoteMedicina::create([
            'inventario_medicina_id' => $medicina->id,
            'item_adquisicion_id'    => $itemAdquisicionId,
            'codigo_lote'            => $codigoLote ?: null,
            'fecha_caducidad'        => $fechaCaducidad ?: null,
            'cantidad_ingresada'     => $cantidad,
            'stock_actual'           => $cantidad,
        ]);

        $medicina->stock_actual += $cantidad;
        $medicina->save();

        return $lote;
    }

    /**
     * Saca unidades siguiendo FEFO: primero lo que caduca antes.
     *
     * Puede repartirse entre varios lotes, que es justo lo que antes no se
     * podía expresar: treinta unidades pueden salir veinte de un lote y diez
     * de otro, y el kardex tiene que poder decirlo.
     *
     * @return Reparto
     */
    public function consumirFefo(
        InventarioMedicina $medicina,
        int $cantidad
    ): array {
        return $this->consumir($medicina, $cantidad, fefo: true);
    }

    /**
     * Devuelve al lote lo que aportó una entrada que se anula.
     *
     * Solo procede si el lote sigue íntegro. Si ya salió parte de él, revertir
     * la cantidad completa afirmaría que volvió al estante algo que
     * físicamente ya no está.
     */
    public function revertirIngreso(LoteMedicina $lote, int $cantidad): void
    {
        if ($lote->stock_actual < $cantidad) {
            throw new ReglaNegocioException(
                "No se puede revertir el lote {$lote->etiqueta}: entraron " .
                "{$cantidad} unidades y quedan {$lote->stock_actual}."
            );
        }

        $lote->stock_actual -= $cantidad;
        $lote->save();

        $medicina = $lote->medicina;
        $medicina->stock_actual -= $cantidad;
        $medicina->save();
    }

    /**
     * Lleva el stock de una medicina a una cantidad exacta.
     *
     * Al subir, las unidades de más abren un lote sin identificar: nadie sabe
     * de qué lote son, y decir que son del último que entró sería inventarlo.
     *
     * Al bajar se retira **lo que caduca más tarde**, al revés que un despacho.
     * Un ajuste a la baja dice que faltan unidades sin saber cuáles; dejar en
     * los libros las de caducidad más próxima hace que el sistema siga
     * avisando de ellas, y si resulta que tampoco están, otro ajuste lo
     * corrige. Retirar las próximas a caducar haría lo contrario: callar sobre
     * existencias que quizá siguen en el estante. Entre avisar de más y callar,
     * una farmacia avisa de más.
     *
     * @return Reparto lo que se retiró; vacío si el ajuste fue al alza
     */
    public function ajustarA(
        InventarioMedicina $medicina,
        int $nuevoStock
    ): array {
        $diferencia = $nuevoStock - $medicina->stock_actual;

        if ($diferencia > 0) {
            $this->ingresar(
                $medicina,
                $diferencia,
                codigoLote: null,
                fechaCaducidad: null
            );

            return [];
        }

        return $this->consumir($medicina, abs($diferencia), fefo: false);
    }

    /**
     * Comprueba el invariante de una medicina.
     *
     * Existe para los tests y para poder auditarlo desde tinker sin repetir la
     * consulta a mano.
     */
    public function cuadra(InventarioMedicina $medicina): bool
    {
        // Los dos lados se leen de la base, no del objeto en memoria: un
        // comprobador de invariantes que se pueda engañar con una instancia
        // desactualizada no comprueba nada.
        $enFicha = (int) InventarioMedicina::whereKey($medicina->getKey())
            ->value('stock_actual');

        $enLotes = (int) LoteMedicina::where(
            'inventario_medicina_id', $medicina->getKey()
        )->sum('stock_actual');

        return $enFicha === $enLotes;
    }

    /** @return Reparto */
    private function consumir(
        InventarioMedicina $medicina,
        int $cantidad,
        bool $fefo
    ): array {
        if ($cantidad <= 0) {
            throw new ReglaNegocioException(
                'La cantidad que sale debe ser mayor a cero.'
            );
        }

        if ($medicina->stock_actual < $cantidad) {
            throw new ReglaNegocioException(
                "Stock insuficiente de {$medicina->nombre}: se piden " .
                "{$cantidad} unidades y quedan {$medicina->stock_actual}."
            );
        }

        $query = $medicina->lotes()->conStock()->lockForUpdate();

        $lotes = $fefo
            ? $query->fefo()->get()
            : $query->orderByRaw('fecha_caducidad IS NULL')
                    ->orderByDesc('fecha_caducidad')
                    ->orderByDesc('id')
                    ->get();

        $reparto  = [];
        $pendiente = $cantidad;

        foreach ($lotes as $lote) {
            if ($pendiente === 0) {
                break;
            }

            $sale = min($pendiente, $lote->stock_actual);

            $lote->stock_actual -= $sale;
            $lote->save();

            $reparto[]  = ['lote' => $lote, 'cantidad' => $sale];
            $pendiente -= $sale;
        }

        // El stock de la ficha decía que había más de lo que suman los lotes.
        // Es el invariante roto, y parar aquí es mejor que repartir de menos y
        // dejar el desajuste escondido en el kardex.
        if ($pendiente > 0) {
            throw new ReglaNegocioException(
                "Los lotes de {$medicina->nombre} no cubren las {$cantidad} " .
                'unidades que dice su stock. Revise el inventario antes de ' .
                'continuar.'
            );
        }

        $medicina->stock_actual -= $cantidad;
        $medicina->save();

        return $reparto;
    }
}
