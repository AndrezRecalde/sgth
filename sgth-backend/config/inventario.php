<?php

return [
    /*
    | Hasta qué día se acepta el número de soporte del sistema antiguo de TIC
    | al registrar una entrega, un traspaso o una devolución
    | (docs/diseno-subsistema-tic.md, D1). Mientras la mesa de ayuda nueva no
    | arranque, los técnicos siguen atendiendo con el sistema antiguo; desde
    | esta fecha lo que se pide es el ticket nuevo y el número viejo queda
    | solo como historia.
    */
    'corte_soporte_anterior' => env('INVENTARIO_CORTE_SOPORTE_ANTERIOR', '2027-01-01'),
];
