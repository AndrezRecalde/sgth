<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\AtencionEnfermeriaServiceInterface;
use App\Models\Dispensario\AtencionEnfermeria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class AtencionEnfermeriaService implements AtencionEnfermeriaServiceInterface
{
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = AtencionEnfermeria::with([
            'enfermera', 'servidor', 'cargaFamiliar.servidor',
            'catalogoServicio',
        ])->orderBy('atendido_en', 'desc');

        if (!empty($filtros['enfermera_id'])) {
            $query->where('enfermera_id', $filtros['enfermera_id']);
        }

        if (!empty($filtros['fecha'])) {
            $query->whereDate('atendido_en', $filtros['fecha']);
        }

        return $query->paginate($filtros['per_page'] ?? 20);
    }

    public function registrar(
        array $datos,
        int $enfermeraId
    ): AtencionEnfermeria {
        return DB::transaction(function () use ($datos, $enfermeraId) {
            $folio = $this->generarFolio();

            $atencion = AtencionEnfermeria::create([
                ...$datos,
                'folio'        => $folio,
                'enfermera_id' => $enfermeraId,
                'atendido_en'  => now(),
                'created_by'   => $enfermeraId,
            ]);

            return $atencion->load([
                'enfermera', 'servidor', 'cargaFamiliar.servidor',
                'catalogoServicio',
            ]);
        });
    }

    /**
     * El folio sale del mayor ya emitido, no de contar filas.
     *
     * Contar da el número correcto solo mientras no falte ninguna fila, y aquí
     * faltan de dos maneras: la tabla borra en blando —una fila retirada baja
     * el conteo y el siguiente folio repite uno ya emitido, que choca contra el
     * índice único porque el borrado en blando no libera el valor— y dos
     * registros simultáneos leen el mismo conteo. Por eso se miran también las
     * borradas y se serializa con un bloqueo de aviso, que suelta el cierre de
     * la transacción.
     */
    private function generarFolio(): string
    {
        $anio = now()->year;

        DB::select('SELECT pg_advisory_xact_lock(?)', [
            crc32("atencion_enfermeria_folio_{$anio}"),
        ]);

        $ultimoFolio = AtencionEnfermeria::withTrashed()
            ->where('folio', 'like', "ENF-{$anio}-%")
            ->max('folio');

        $ultimoSecuencial = $ultimoFolio
            ? (int) substr($ultimoFolio, strlen("ENF-{$anio}-"))
            : 0;

        $secuencial = str_pad(
            (string) ($ultimoSecuencial + 1), 5, '0', STR_PAD_LEFT
        );

        return "ENF-{$anio}-{$secuencial}";
    }
}
