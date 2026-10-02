<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * En qué punto de su ventana está una campaña de tamizaje —la psicosocial y
 * la del ASSIST—, que no es lo mismo que su columna `activa`.
 *
 * `activa` es una sola cosa: si alguien la cerró a mano. La ventana la
 * componen tres datos, y antes cada lector miraba los que le parecía:
 *
 * - Los dos servicios comprobaban `activa` y `fecha_cierre`, nunca
 *   `fecha_apertura`. Una campaña creada con apertura el mes que viene se
 *   respondía hoy con su enlace. Si la fecha no manda, el campo es decorativo.
 * - La comparación del cierre era `fecha_cierre->isPast()`, y la columna está
 *   casteada a `date`: a las 00:00 del día de cierre ya era pasado. El último
 *   día de toda campaña era inservible — quien la programaba hasta el 31
 *   perdía el 31 entero.
 * - La pantalla, por su parte, pintaba `activa ? 'Abierta' : 'Cerrada'`, así
 *   que una campaña con la apertura en el futuro salía «Abierta» y una con el
 *   cierre ya pasado también, mientras el enlace público rechazaba a quien
 *   entraba.
 *
 * El cálculo vive aquí, es puro y se prueba sin base de datos. Los dos
 * servicios lo usan para rechazar con el mensaje correcto y los dos modelos
 * lo publican en `$appends` para que la pantalla diga la verdad.
 */
enum EstadoCampaniaSso: string
{
    /** Tiene fecha de apertura futura: existe, pero todavía no se responde. */
    case PROGRAMADA = 'programada';

    /** Dentro de la ventana y sin cerrar: admite respuestas. */
    case ABIERTA = 'abierta';

    /** Cerrada a mano, o con la fecha de cierre ya pasada. */
    case CERRADA = 'cerrada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PROGRAMADA => 'Programada',
            self::ABIERTA    => 'Abierta',
            self::CERRADA    => 'Cerrada',
        };
    }

    /** Lo que decide si el cuestionario público acepta una respuesta. */
    public function admiteRespuestas(): bool
    {
        return $this === self::ABIERTA;
    }

    /**
     * El estado de una campaña a partir de los tres datos de su ventana.
     *
     * Las dos fechas son días, no instantes: la columna está casteada a
     * `date`. Así que la comparación es por día y el cierre es INCLUSIVO —se
     * responde durante todo el día de cierre—, que es la lectura natural de
     * «abierta hasta el 31» y la que no tira un día a la basura.
     *
     * `$hoy` entra como parámetro para poder probar la ventana sin congelar el
     * reloj del proceso.
     */
    public static function desde(
        bool $activa,
        ?CarbonInterface $fechaApertura,
        ?CarbonInterface $fechaCierre,
        ?CarbonInterface $hoy = null,
    ): self {
        $hoy = ($hoy ?? Carbon::now())->copy()->startOfDay();

        if (! $activa) {
            return self::CERRADA;
        }

        if ($fechaCierre !== null && $fechaCierre->copy()->startOfDay()->lessThan($hoy)) {
            return self::CERRADA;
        }

        if ($fechaApertura !== null && $fechaApertura->copy()->startOfDay()->greaterThan($hoy)) {
            return self::PROGRAMADA;
        }

        return self::ABIERTA;
    }
}
