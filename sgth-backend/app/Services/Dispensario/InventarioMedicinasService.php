<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\InventarioMedicinasServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\LoteMedicina;
use App\Models\Dispensario\MovimientoInventarioMed;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class InventarioMedicinasService implements InventarioMedicinasServiceInterface
{
    public function __construct(
        private readonly StockPorLotes $stock
    ) {}

    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = InventarioMedicina::conResumenDeLotes()
            ->orderBy('created_at', 'desc');

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
            $query->bajoMinimo();
        }

        return $query->paginate($filtros['per_page'] ?? 20);
    }

    public function obtener(int $id): InventarioMedicina
    {
        return InventarioMedicina::conResumenDeLotes()
            ->with(['lotes' => fn ($q) => $q->conStock()->fefo()])
            ->findOrFail($id);
    }

    /** Días de antelación con que se avisa de una caducidad próxima. */
    public const DIAS_AVISO_CADUCIDAD = 60;

    /**
     * Lo que la farmacia necesita atender hoy, en tres grupos.
     *
     * Las caducadas van aparte de las próximas a caducar: desde que el despacho
     * las rechaza son existencias inmovilizadas, y lo que corresponde con ellas
     * es darlas de baja, no reponerlas. Antes se quedaban fuera del aviso, que
     * solo miraba de hoy en adelante.
     *
     * En caducidad solo entra lo que tiene existencias: una medicina vencida
     * con stock cero no pide ninguna acción.
     *
     * Bajo mínimo va por medicina, porque reponer es una decisión del producto.
     * Caducidad va por LOTE, porque lo que caduca es el lote: una fila por
     * medicina con una sola fecha era justamente el error que arrastraba el
     * módulo, y dejaba tapado un lote a punto de vencer detrás de otro más
     * reciente.
     *
     * @return array{bajo_minimo: Collection, por_caducar: Collection, caducadas: Collection}
     */
    public function resumenAlertas(): array
    {
        $limite = now()->addDays(self::DIAS_AVISO_CADUCIDAD)->toDateString();

        $lotesDeActivas = fn () => LoteMedicina::with('medicina')
            ->conStock()
            ->whereHas('medicina', fn ($q) => $q->where('estado', true));

        return [
            // Sobre lo despachable: ochenta unidades vencidas no reponen nada.
            'bajo_minimo' => InventarioMedicina::where('estado', true)
                ->conResumenDeLotes()
                ->bajoMinimo()
                ->orderBy('nombre')
                ->get(),

            'caducadas' => $lotesDeActivas()
                ->caducados()
                ->fefo()
                ->get(),

            'por_caducar' => $lotesDeActivas()
                ->vigentes()
                ->whereNotNull('fecha_caducidad')
                ->whereDate('fecha_caducidad', '<=', $limite)
                ->fefo()
                ->get(),
        ];
    }

    /**
     * Cuántas medicinas están bajo mínimo, para la insignia del menú.
     *
     * Solo cuenta las activas, igual que el job de alertas y el tablero. El
     * frontend lo pedía reutilizando el listado sin filtrar por estado, así que
     * la insignia incluía medicinas retiradas del catálogo y los tres números
     * discrepaban entre sí. Reponer lo que ya no se despacha no urge.
     */
    public function contarStockBajo(): int
    {
        return InventarioMedicina::where('estado', true)
            ->bajoMinimo()
            ->count();
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
            ->conResumenDeLotes()
            // Despachable es tener al menos un lote vigente con existencias, no
            // que el campo de la ficha diga una fecha futura: con lotes
            // mezclados ese campo dejaba fuera stock bueno y colaba stock
            // vencido, según cuál hubiera entrado el último.
            ->when($soloDespachables, fn ($q) => $q
                ->whereHas('lotes', fn ($sub) => $sub->conStock()->vigentes()))
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
        // La transacción no es por el `create`, que es una sola escritura, sino
        // por el bloqueo que toma `generarCodigo`: se libera al cerrarla.
        return DB::transaction(fn () => InventarioMedicina::create([
            ...$datos,
            'stock_actual' => 0,
            'codigo'       => $this->generarCodigo(),
            'created_by'   => $registradoPor,
        ]));
    }

    /**
     * Siguiente código del catálogo.
     *
     * Se toma el MÁXIMO de los que siguen el patrón, no el código del último
     * id: si alguna vez entra una fila con otro formato, `(int)` la leía como
     * cero y el siguiente código chocaría contra el índice único. Se miran
     * también las borradas, para no reutilizar un código ya emitido.
     *
     * El bloqueo de aviso serializa leer el máximo y escribir el nuevo código
     * entre altas simultáneas, y lo libera el cierre de la transacción.
     */
    private function generarCodigo(): string
    {
        DB::select('SELECT pg_advisory_xact_lock(?)', [
            crc32('inventario_medicina_codigo'),
        ]);

        $ultimoCodigo = InventarioMedicina::withTrashed()
            ->where('codigo', 'like', 'MED-%')
            ->max('codigo');

        $ultimoSecuencial = $ultimoCodigo
            ? (int) substr($ultimoCodigo, strlen('MED-'))
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
        int $registradoPor,
        ?int $loteId = null
    ): InventarioMedicina {
        if ($cantidad <= 0) {
            throw new ReglaNegocioException(
                'La cantidad a dar de baja debe ser mayor a cero.'
            );
        }

        return DB::transaction(function () use (
            $id, $cantidad, $motivo, $registradoPor, $loteId
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

            // Con lote elegido sale de ese y solo de ese: una caja rota o un
            // lote retirado por el fabricante son de uno concreto, y hacerlo
            // salir por FEFO anotaría una mentira en el kardex. Sin elegir,
            // FEFO, que es lo que sirve para tirar lo vencido.
            $reparto = $loteId
                ? $this->stock->consumirDeLote(
                    $medicina,
                    LoteMedicina::findOrFail($loteId),
                    $cantidad
                )
                : $this->stock->consumirFefo($medicina, $cantidad);

            $this->anotarEnKardex(
                $medicina, $reparto, 'baja', $motivo, $registradoPor
            );

            return $medicina;
        });
    }

    /**
     * Un movimiento por lote tocado.
     *
     * Una salida puede repartirse entre varios lotes, y el kardex tiene que
     * poder decirlo: son unidades distintas, con caducidades distintas, las
     * que salieron del estante.
     *
     * @param list<array{lote: \App\Models\Dispensario\LoteMedicina, cantidad: int}> $reparto
     */
    private function anotarEnKardex(
        InventarioMedicina $medicina,
        array $reparto,
        string $tipo,
        string $motivo,
        int $registradoPor
    ): void {
        // El stock ya está descontado, así que el corrido se reconstruye hacia
        // adelante desde antes de la salida: cada fila deja el resultante que
        // le toca, y la última coincide con el stock de la ficha.
        $restante = $medicina->stock_actual
            + array_sum(array_column($reparto, 'cantidad'));

        foreach ($reparto as $salida) {
            $restante -= $salida['cantidad'];

            MovimientoInventarioMed::create([
                'inventario_medicina_id' => $medicina->id,
                'lote_id'                => $salida['lote']->id,
                'tipo_movimiento'        => $tipo,
                'cantidad'               => -$salida['cantidad'],
                'stock_resultante'       => $restante,
                // El lote va en su columna, no dentro del texto: repetirlo aquí
                // sería duplicar lo que el kardex ya muestra en su fila.
                'motivo'                 => $motivo,
                'registrado_por'         => $registradoPor,
            ]);
        }
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

            $reparto = $this->stock->ajustarA($medicina, $nuevoStock);

            if ($diferencia > 0) {
                // Al alza las unidades entran en un lote sin identificar: nadie
                // sabe de cuál son, y atribuirlas al último que entró sería
                // inventarlo.
                MovimientoInventarioMed::create([
                    'inventario_medicina_id' => $medicina->id,
                    'lote_id'                => $medicina->lotes()
                        ->latest('id')->value('id'),
                    'tipo_movimiento'        => 'ajuste',
                    'cantidad'               => $diferencia,
                    'stock_resultante'       => $medicina->stock_actual,
                    'motivo'                 => $motivo,
                    'registrado_por'         => $registradoPor,
                ]);
            } else {
                $this->anotarEnKardex(
                    $medicina, $reparto, 'ajuste', $motivo, $registradoPor
                );
            }

            return $medicina;
        });
    }

    public function darDeBaja(int $id): InventarioMedicina
    {
        $medicina = InventarioMedicina::findOrFail($id);
        $medicina->update(['estado' => !$medicina->estado]);
        return $medicina;
    }

    /**
     * El kardex se pagina porque no deja de crecer: cada entrada, despacho,
     * baja, ajuste y anulación es una fila más, y ninguna se borra —es el libro
     * inmutable del inventario—. Traerlo entero era barato el primer mes y deja
     * de serlo solo.
     */
    public function kardex(int $id, int $porPagina = 20): LengthAwarePaginator
    {
        InventarioMedicina::findOrFail($id);

        return MovimientoInventarioMed::where(
            'inventario_medicina_id', $id
        )
            ->with(['registrador', 'lote'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($porPagina);
    }
}
