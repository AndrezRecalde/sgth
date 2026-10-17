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

    /*
    | A quién va la «Solicitud de materiales informáticos a bodega» (fase
    | 5.4c): la unidad de la que depende Bodega, por su código en la estructura.
    | Su jefe firma «Autorizado por».
    */
    'unidad_bodega_codigo' => env('INVENTARIO_UNIDAD_BODEGA_CODIGO', 'GADPE-01'),

    /*
    | Quién firma por Activos Fijos y Bodega el acta de constatación física
    | cuando esa unidad participa en el recorrido (fase 5.2b, P13): el jefe
    | de la unidad con este código en la estructura, sellado al cerrar.
    */
    'unidad_activos_fijos_codigo' => env('INVENTARIO_UNIDAD_ACTIVOS_FIJOS_CODIGO', 'GADPE-01-01'),

    /*
    | La carga del Excel de TIC (fase 1.4): de las siglas de la columna
    | «AREA» a la unidad administrativa, por su nombre en la estructura. TIC
    | revisa esta tabla antes de la carga real; lo que no esté aquí, o no
    | exista con ese nombre, sale en el informe.
    */
    'carga' => [
        'areas' => [
            'ADMINISTRATIVO'            => 'Gestión Administrativa',
            'AMBIENTAL'                 => 'Gestión Ambiental',
            'ASESORIA LEGAL'            => 'Asesoría Legal',
            'AUDITORIA INTERNA'         => 'Unidad de Gestión de Auditoría Interna',
            'CALIDAD'                   => 'Unidad de Gestión de Calidad',
            'COMPRAS PUBLICAS'          => 'Unidad de Gestión de Contratación Pública',
            'COMUNICACION'              => 'Gestión de Comunicación Social',
            'COORDINACION INSTITUCIONAL' => 'Gestión de Coordinación Institucional',
            'CRD'                       => 'Gestión de Cuencas, Riego y Drenaje',
            'DASIP'                     => 'Gestión de Acción Social, Inclusión y Participación',
            'DISPENSARIO MEDICO'        => 'Dispensario Médico',
            'EN TIC'                    => 'Gestión de Tecnologías de la Información y Comunicación',
            'FINANCIERO'                => 'Gestión Financiera',
            'FISCALIZACION'             => 'Gestión de Fiscalización',
            'FODEPRO'                   => 'Gestión de Fomento y Desarrollo Productivo',
            'GACIT'                     => 'Gestión de Relaciones Internacionales y Cooperación',
            'INFRAESTRUCTURA'           => 'Gestión de Infraestructura Vial',
            'PLANIFICACION'             => 'Gestión de Planificación',
            'PREFECTURA'                => 'Prefectura Provincial',
            'SECRETARIA GENERAL'        => 'Gestión de Secretaría General',
            'TIC'                       => 'Gestión de Tecnologías de la Información y Comunicación',
            'TTHH'                      => 'Gestión de Talento Humano y Riesgos Laborales',
            'VICEPREFECTURA'            => 'Viceprefectura Provincial',
        ],

        // La columna «PISO»: «PB» y «1ER»…«6TO» son del Edificio Central; lo
        // demás es otra sede. UNAMY ya no existe: sus filas quedan sin
        // ubicación y salen en el informe.
        'sede_principal' => 'Edificio Central',
        'sedes' => [
            'SM' => 'San Mateo',
            'DM' => 'Dispensario Médico',
        ],
    ],
];
