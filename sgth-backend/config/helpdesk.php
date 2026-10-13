<?php

/*
| La mesa de ayuda de TIC (docs/diseno-subsistema-tic.md, fase 3).
*/

return [

    /*
    | La unidad de TIC en la estructura orgánica, por su código. Sus
    | subunidades —Soporte Técnico, Infraestructura y Redes, Desarrollo de
    | Software, Seguridad de la Información— son las áreas que atienden (D6).
    */
    'unidad_tic_codigo' => env('HELPDESK_UNIDAD_TIC_CODIGO', 'GADPE-15'),

    /*
    | La jornada de TIC: el SLA corre en horas hábiles, de lunes a viernes, sin
    | los feriados institucionales (fase 3.2).
    */
    'jornada' => [
        'inicio' => env('HELPDESK_JORNADA_INICIO', '08:00'),
        'fin'    => env('HELPDESK_JORNADA_FIN', '17:00'),
    ],

    /*
    | El SLA por prioridad, en horas hábiles, mientras TIC no cargue su tabla
    | (D7). Una fila de `slas` para esa prioridad —general o de una categoría—
    | manda sobre esto.
    */
    'sla' => [
        'urgente' => ['respuesta' => 1, 'resolucion' => 4],
        'alta'    => ['respuesta' => 2, 'resolucion' => 9],
        'media'   => ['respuesta' => 4, 'resolucion' => 18],
        'baja'    => ['respuesta' => 9, 'resolucion' => 45],
    ],

    /* La alerta sale cuando queda este tanto del plazo de resolución. */
    'alerta_restante' => 0.25,

    /*
    | Un ticket resuelto que el solicitante no confirma ni reabre se cierra solo
    | a los N días hábiles.
    */
    'cierre_automatico_dias_habiles' => (int) env('HELPDESK_CIERRE_AUTOMATICO_DIAS', 3),

    /*
    | Los archivos de la conversación: capturas, registros, documentos. Van al
    | disco privado y se descargan con el permiso de ver el ticket.
    */
    'adjuntos' => [
        'max_kb' => 10240,
        'tipos'  => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'log', 'docx', 'xlsx'],
    ],
];
