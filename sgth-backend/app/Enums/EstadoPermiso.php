<?php

namespace App\Enums;

enum EstadoPermiso: string
{
    case PENDIENTE           = 'pendiente';
    case ACTIVO              = 'activo';
    case ANULADO             = 'anulado';
    case RECHAZADO           = 'rechazado';
    case FALTA_INJUSTIFICADA = 'falta_injustificada';
    case VALIDADO_TRABAJO_SOCIAL = 'validado_trabajo_social';

    /**
     * Los estados en los que el permiso SE CONCEDIÓ, y por lo tanto cuentan
     * como tiempo de ausencia autorizada.
     *
     * Se dice en positivo y no como «todos menos anulado y pendiente». Con
     * aquella forma, `RECHAZADO` y `FALTA_INJUSTIFICADA` entraban en la
     * cuenta: una falta injustificada es exactamente el permiso que NO se
     * concedió, y sumaba horas. Un enum que crece —y este creció hasta seis
     * casos— rompe la forma negativa sin que nada avise; la positiva sigue
     * diciendo lo mismo cuando aparece el séptimo.
     *
     * Vive aquí y no en un servicio porque hay dos lectores que tienen que
     * coincidir: el consolidado de permisos de Asistencia
     * (`ConsolidadoPermisoService`), que es el informe que firma Talento
     * Humano, y el indicador de Ausentismo por Enfermedad de Riesgos
     * Laborales (`DashboardSsoService`). Cuando estaban escritos por separado
     * no coincidían, y las dos pantallas daban cifras distintas del mismo
     * período.
     *
     * @return list<string>
     */
    public static function concedidos(): array
    {
        return [
            self::ACTIVO->value,
            self::VALIDADO_TRABAJO_SOCIAL->value,
        ];
    }
}
