<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\AtencionEnfermeriaServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\AtencionEnfermeria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class AtencionEnfermeriaService implements AtencionEnfermeriaServiceInterface
{
    /**
     * Relaciones de cada fila. Enfermera y anulador van con su servidor y las
     * columnas que pide `nombre_completo`: el accesor lee `servidor` y, sin
     * cargarlo, hacía dos consultas más por fila del listado.
     */
    private const RELACIONES = [
        'enfermera:id,usuario_ti,email,servidor_id',
        'enfermera.servidor:id,nombre,apellido',
        'anulador:id,usuario_ti,email,servidor_id',
        'anulador.servidor:id,nombre,apellido',
        'servidor', 'cargaFamiliar.servidor', 'catalogoServicio',
    ];

    public function listar(array $filtros): LengthAwarePaginator
    {
        // Las anuladas siguen en la lista, marcadas: la trazabilidad es
        // precisamente poder ver que algo se registró y luego se anuló, y por
        // qué. Quien solo quiera las vigentes filtra por `solo_vigentes`.
        $query = AtencionEnfermeria::with(self::RELACIONES)
            ->orderBy('atendido_en', 'desc')
            ->orderBy('id', 'desc');

        if (!empty($filtros['solo_vigentes'])) {
            $query->whereNull('anulado_en');
        }

        if (!empty($filtros['enfermera_id'])) {
            $query->where('enfermera_id', $filtros['enfermera_id']);
        }

        if (!empty($filtros['fecha'])) {
            // Entre el inicio y el fin del día, no `whereDate`: envolver la
            // columna en una función impide usar su índice.
            $dia = \Carbon\Carbon::parse($filtros['fecha']);
            $query->whereBetween('atendido_en', [$dia->copy()->startOfDay(), $dia->copy()->endOfDay()]);
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

            return $atencion->load(self::RELACIONES);
        });
    }

    /**
     * Anular una atención: se marca, no se borra.
     *
     * Es un registro clínico —dice que a alguien se le puso una inyección— así
     * que la fila se queda con quién la anuló, cuándo y por qué. Antes no había
     * forma de deshacer nada: una atención apuntada al paciente equivocado se
     * quedaba ahí para siempre.
     *
     * La anula quien la registró. La administración del Dispensario puede
     * anular cualquiera; el resto no: antes cualquier usuario con sesión podía
     * anular la atención de otra enfermera de hace meses.
     */
    public function anular(
        int $id,
        string $motivo,
        int $anuladoPor,
        bool $puedeAnularAjenas = false,
    ): AtencionEnfermeria {
        return DB::transaction(function () use ($id, $motivo, $anuladoPor, $puedeAnularAjenas) {
            $atencion = AtencionEnfermeria::lockForUpdate()->findOrFail($id);

            if ($atencion->estaAnulada()) {
                throw new ReglaNegocioException(
                    "La atención {$atencion->folio} ya fue anulada."
                );
            }

            if (!$puedeAnularAjenas && (int) $atencion->enfermera_id !== $anuladoPor) {
                throw new ReglaNegocioException(
                    'Solo quien registró la atención, o la administración del Dispensario, puede anularla.'
                );
            }

            $atencion->update([
                'anulado_en'       => now(),
                'anulado_por'      => $anuladoPor,
                'motivo_anulacion' => $motivo,
            ]);

            return $atencion->load(self::RELACIONES);
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
