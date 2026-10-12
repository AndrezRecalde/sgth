<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\TipoMovimientoPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Helpers\DiasHabilesHelper;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * La posesión y el plazo para presentarse (diseño de Acciones de Personal, 6.4;
 * fase 6.4).
 *
 * El nombramiento queda insubsistente si quien ingresa no se presenta a
 * trabajar en el término de 3 días desde el registro de la acción, salvo que
 * las circunstancias geográficas pidan más, hasta 5 días laborables; y el
 * contrato ocasional se termina si el contratado no se presenta en 24 horas
 * desde el registro del contrato (Reglamento a la LOSEP, Art. 20). El sistema
 * no anula solo: avisa, y Talento Humano anula.
 *
 * El plazo corre desde el registro o, si es posterior, desde el día en que
 * rige: nadie se presenta a un puesto antes de que su nombramiento rija. Se
 * cuenta en días hábiles, con los feriados de la institución, porque la ley
 * dice «término». Las 24 horas del ocasional son el día hábil siguiente.
 *
 * La posesión la registra Talento Humano: es el «se presentó» del plazo y la
 * fecha que imprime el documento. La llevan también el ascenso, el
 * nombramiento definitivo y el encargo, pero sin plazo: quien los recibe ya
 * trabaja en la institución.
 */
class PosesionService
{
    use DiasHabilesHelper;

    public const DIAS_POR_DEFECTO = 3;

    public const DIAS_MAXIMOS = 5;

    /** ¿Corre el plazo? El ingreso por acción de personal y el del ocasional por su contrato. */
    public static function tienePlazo(?TipoMovimientoPersonal $tipo, ?InstrumentoActo $instrumento): bool
    {
        return $tipo === TipoMovimientoPersonal::INGRESO
            && in_array($instrumento, [InstrumentoActo::ACCION_PERSONAL, InstrumentoActo::REGISTRO_CONTRATO], true);
    }

    /**
     * ¿Se le registra la posesión? Las clases que ponen al servidor en un
     * puesto, con acción de personal, y el ingreso del ocasional. El obrero,
     * Servicios Profesionales y las autoridades tienen sus propios documentos.
     */
    public static function admitePosesion(MovimientoPersonal $movimiento): bool
    {
        $instrumento = $movimiento->instrumento ?? InstrumentoActo::ACCION_PERSONAL;

        return ($movimiento->llevaPosesion() && $instrumento === InstrumentoActo::ACCION_PERSONAL)
            || self::tienePlazo($movimiento->tipo_movimiento, $instrumento);
    }

    /**
     * Los días del borrador. Solo los tiene el ingreso por acción de personal:
     * 3 por defecto, y más solo con la razón. En cualquier otro acto se borran:
     * el ocasional tiene sus 24 horas, y el resto no tiene plazo.
     *
     * @return array{dias_para_presentarse: ?int, razon_dias_adicionales: ?string}
     */
    public static function diasDelBorrador(
        ?TipoMovimientoPersonal $tipo,
        ?InstrumentoActo $instrumento,
        mixed $dias,
        mixed $razon,
    ): array {
        if ($tipo !== TipoMovimientoPersonal::INGRESO || $instrumento !== InstrumentoActo::ACCION_PERSONAL) {
            return ['dias_para_presentarse' => null, 'razon_dias_adicionales' => null];
        }

        $dias = $dias === null || $dias === '' ? self::DIAS_POR_DEFECTO : (int) $dias;
        $razon = is_string($razon) ? trim($razon) : null;

        if ($dias < self::DIAS_POR_DEFECTO || $dias > self::DIAS_MAXIMOS) {
            throw new ReglaNegocioException(
                'El término para presentarse es de 3 días, o de hasta 5 días laborables si lo piden las '
                    .'circunstancias geográficas (Reglamento a la LOSEP, Art. 20).'
            );
        }

        if ($dias > self::DIAS_POR_DEFECTO && blank($razon)) {
            throw new ReglaNegocioException(
                'Indique las circunstancias geográficas que piden más de 3 días para presentarse '
                    .'(Reglamento a la LOSEP, Art. 20).'
            );
        }

        return [
            'dias_para_presentarse'  => $dias,
            'razon_dias_adicionales' => $dias > self::DIAS_POR_DEFECTO ? $razon : null,
        ];
    }

    /**
     * Hasta qué día puede presentarse, inclusive. Se calcula al registrar y se
     * guarda: un feriado que se declare después no lo mueve. Null si el acto
     * no tiene plazo.
     */
    public function venceEl(MovimientoPersonal $movimiento): ?Carbon
    {
        $instrumento = $movimiento->instrumento ?? InstrumentoActo::ACCION_PERSONAL;

        if (! self::tienePlazo($movimiento->tipo_movimiento, $instrumento) || $movimiento->fecha_registro === null) {
            return null;
        }

        $registro = Carbon::parse($movimiento->fecha_registro)->startOfDay();
        $rige = Carbon::parse($movimiento->fecha_efectiva)->startOfDay();
        $desde = $rige->greaterThan($registro) ? $rige : $registro;

        $dias = $instrumento === InstrumentoActo::REGISTRO_CONTRATO
            ? 1
            : ($movimiento->dias_para_presentarse ?? self::DIAS_POR_DEFECTO);

        return $this->calcularDiasHabiles($desde, $dias);
    }

    /**
     * Registra la posesión: una sola vez, con la acción ya registrada, no
     * antes del día en que rige ni después de hoy. Si se presentó fuera del
     * plazo, se registra igual —decidir si el nombramiento sigue es de
     * Talento Humano— y la ficha lo marca.
     */
    public function registrar(MovimientoPersonal $movimiento, string $fecha, ?string $lugar, User $usuario): MovimientoPersonal
    {
        TramiteSobreSiMismo::impedir($movimiento->servidor_id);

        if (! self::admitePosesion($movimiento)) {
            throw new ReglaNegocioException(
                "«{$movimiento->etiqueta()}» no lleva posesión: solo el ingreso, el ascenso, el nombramiento "
                    .'definitivo y el encargo.'
            );
        }

        if (! in_array($movimiento->estado, [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA], true)) {
            throw new ReglaNegocioException('La posesión se registra cuando la acción ya está registrada.');
        }

        if ($movimiento->fecha_posesion !== null) {
            throw new ReglaNegocioException(
                'La posesión ya está registrada, el '.$movimiento->fecha_posesion->format('d/m/Y')
                    .'. Si tiene un error, anótelo en la acción.'
            );
        }

        $dia = Carbon::parse($fecha)->startOfDay();

        if ($dia->lessThan($movimiento->fecha_efectiva->copy()->startOfDay())) {
            throw new ReglaNegocioException(
                'La posesión no puede ser anterior al día en que rige la acción, el '
                    .$movimiento->fecha_efectiva->format('d/m/Y').'.'
            );
        }

        if ($dia->greaterThan(today())) {
            throw new ReglaNegocioException('La posesión no puede tener una fecha futura.');
        }

        // El ocasional no se posesiona: se presenta a laborar, y su registro
        // del contrato no tiene documento que imprima el lugar.
        $esContrato = $movimiento->instrumento === InstrumentoActo::REGISTRO_CONTRATO;

        if (! $esContrato && blank($lugar)) {
            throw new ReglaNegocioException('Indique el lugar de la posesión: lo imprime el documento.');
        }

        $movimiento->fecha_posesion = $dia->toDateString();
        $movimiento->lugar_posesion = $esContrato ? null : trim((string) $lugar);
        $movimiento->posesion_registrada_por = $usuario->id;
        $movimiento->save();

        return $movimiento->fresh();
    }

    /**
     * Los ingresos cuyo plazo venció sin posesión registrada, en `$fecha`: los
     * lista el comando diario para que Talento Humano los anule o registre la
     * posesión que falta.
     *
     * @return Collection<int, MovimientoPersonal>
     */
    public function vencidos(string $fecha): Collection
    {
        return MovimientoPersonal::query()
            ->sinPosesion()
            ->where('presentacion_vence_el', '<', $fecha)
            ->with('servidor:id,nombre,apellido')
            ->orderBy('presentacion_vence_el')
            ->orderBy('id')
            ->get();
    }
}
