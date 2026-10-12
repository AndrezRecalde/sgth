<?php

return [
    /*
    | La nómina del SGTH es referencial (fase 7.3c): Financiero paga en su
    | propio sistema (decisión de TH, 2026-10-04), y la del SGTH se calcula en
    | paralelo para compararla. Una nómina referencial se cierra para
    | congelarla, pero no sale al ERP ni a los servidores. Se apaga cuando
    | Talento Humano y Financiero den por buena la corrida del mes de prueba.
    */
    'referencial' => (bool) env('NOMINA_REFERENCIAL', true),
];
