<?php

namespace App\Enums;

/**
 * Los estados por los que pasa una solicitud de vacaciones.
 *
 * Hasta el 2026-11-24 no había enum: eran cadenas sueltas repartidas por ocho
 * archivos de cuatro módulos. De ahí vienen los dos conjuntos con nombre de
 * abajo. El peligro no es escribir mal una palabra —eso lo ataja la
 * restricción de la base—, sino que alguien agregue un estado y se olvide de
 * uno de los sitios que los leen desde fuera.
 *
 * Los nombres dicen quién concede qué, que es lo que el papel no decía:
 *
 * - AUTORIZADA: el jefe inmediato autorizó, de palabra, y lo confirma firmando
 *   el impreso. La solicitud nace aquí y es el único estado que se imprime.
 *   Los días TODAVÍA no salen del saldo.
 * - REGISTRADA: Talento Humano recibió el papel firmado en ventanilla y lo
 *   registró. Aquí sí se descuentan los días, con los períodos bloqueados.
 * - GOZADA: ya pasaron las fechas. La asigna sola la tarea diaria
 *   `sgth:vacaciones:marcar-gozadas`; nadie la administra.
 * - ANULADA: se deshizo, siempre con motivo. Devuelve los días si ya se
 *   habían descontado.
 *
 * El valor es `autorizada` y no `aprobada` a propósito. «Aprobada» significaba
 * «Talento Humano la aprobó y los días están descontados» en los cinco sitios
 * que leen el estado desde fuera; reutilizar la palabra para el primer estado
 * habría dejado que el sistema diera por ausente a quien todavía no pasó por
 * ventanilla. En pantalla sí se lee «Aprobada por el jefe inmediato», que es
 * lo que la gente entiende (decisión del usuario, 2026-11-24).
 *
 * No existe `rechazada`: si el papel llega mal, Talento Humano lo anula con
 * motivo. Una sola salida.
 */
enum EstadoVacacion: string
{
    case AUTORIZADA = 'autorizada';
    case REGISTRADA = 'registrada';
    case GOZADA     = 'gozada';
    case ANULADA    = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::AUTORIZADA => 'Aprobada por el jefe inmediato',
            self::REGISTRADA => 'Registrada por Talento Humano',
            self::GOZADA     => 'Gozada',
            self::ANULADA    => 'Anulada',
        };
    }

    /**
     * La solicitud sigue viva: ocupa esas fechas en el calendario del servidor.
     *
     * Lo leen los tres sitios que impiden pisar unas vacaciones con otra cosa:
     * `PermisoService` (no se registra un permiso encima), `LicenciaService`
     * (ni una licencia) y el cruce entre solicitudes de `VacacionService`.
     *
     * @return list<string>
     */
    public static function vigentes(): array
    {
        return [
            self::AUTORIZADA->value,
            self::REGISTRADA->value,
            self::GOZADA->value,
        ];
    }

    /**
     * Talento Humano ya las registró: sus días salieron del saldo y la
     * ausencia es firme.
     *
     * Lo leen `PeriodoVacacionService` —para atribuir los días a su período— y
     * `AusenciaDelTitularService`, que decide si hay que subrogar el puesto.
     * Una AUTORIZADA queda fuera a propósito: mientras el papel no llegue a
     * ventanilla, el sistema no descuenta días ni subroga a nadie.
     *
     * @return list<string>
     */
    public static function confirmadas(): array
    {
        return [
            self::REGISTRADA->value,
            self::GOZADA->value,
        ];
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
