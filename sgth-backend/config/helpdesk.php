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
    | Los archivos de la conversación: capturas, registros, documentos. Van al
    | disco privado y se descargan con el permiso de ver el ticket.
    */
    'adjuntos' => [
        'max_kb' => 10240,
        'tipos'  => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'log', 'docx', 'xlsx'],
    ],
];
