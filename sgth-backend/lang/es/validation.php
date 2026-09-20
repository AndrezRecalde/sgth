<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mensajes de validación
    |--------------------------------------------------------------------------
    |
    | La aplicación corre con APP_LOCALE=es y APP_FALLBACK_LOCALE=es. Sin este
    | archivo, toda regla sin mensaje propio en su FormRequest llegaba a la
    | pantalla como la clave cruda: «validation.unique», «validation.max.string».
    |
    | El campo se nombra sin artículo en `attributes` («cédula», «correo
    | personal») y el artículo va en el mensaje, como en «El campo cédula es
    | obligatorio».
    |
    */

    'accepted' => 'Debe aceptar el campo :attribute.',
    'accepted_if' => 'Debe aceptar el campo :attribute cuando :other es :value.',
    'active_url' => 'El campo :attribute no es una URL válida.',
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha igual o posterior a :date.',
    'alpha' => 'El campo :attribute solo puede contener letras.',
    'alpha_dash' => 'El campo :attribute solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num' => 'El campo :attribute solo puede contener letras y números.',
    'any_of' => 'El campo :attribute no es válido.',
    'array' => 'El campo :attribute debe ser una lista.',
    'ascii' => 'El campo :attribute solo puede contener caracteres alfanuméricos y símbolos de un byte.',
    'before' => 'El campo :attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => 'El campo :attribute debe ser una fecha igual o anterior a :date.',
    'between' => [
        'array' => 'El campo :attribute debe tener entre :min y :max elementos.',
        'file' => 'El campo :attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'can' => 'El campo :attribute contiene un valor no permitido.',
    'confirmed' => 'La confirmación del campo :attribute no coincide.',
    'contains' => 'Al campo :attribute le falta un valor obligatorio.',
    'current_password' => 'La contraseña es incorrecta.',
    'date' => 'El campo :attribute no es una fecha válida.',
    'date_equals' => 'El campo :attribute debe ser una fecha igual a :date.',
    'date_format' => 'El campo :attribute no corresponde al formato :format.',
    'decimal' => 'El campo :attribute debe tener :decimal decimales.',
    'declined' => 'Debe rechazar el campo :attribute.',
    'declined_if' => 'Debe rechazar el campo :attribute cuando :other es :value.',
    'different' => 'Los campos :attribute y :other deben ser diferentes.',
    'digits' => 'El campo :attribute debe tener :digits dígitos.',
    'digits_between' => 'El campo :attribute debe tener entre :min y :max dígitos.',
    'dimensions' => 'Las dimensiones de la imagen del campo :attribute no son válidas.',
    'distinct' => 'El campo :attribute tiene un valor repetido.',
    'doesnt_contain' => 'El campo :attribute no puede contener ninguno de estos valores: :values.',
    'doesnt_end_with' => 'El campo :attribute no puede terminar con ninguno de estos valores: :values.',
    'doesnt_start_with' => 'El campo :attribute no puede empezar con ninguno de estos valores: :values.',
    'email' => 'El campo :attribute no es un correo electrónico válido.',
    'encoding' => 'El campo :attribute debe estar codificado en :encoding.',
    'ends_with' => 'El campo :attribute debe terminar con uno de estos valores: :values.',
    'enum' => 'El valor del campo :attribute no está en el catálogo.',
    'exists' => 'El valor del campo :attribute no existe.',
    'extensions' => 'El campo :attribute debe tener una de estas extensiones: :values.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'filled' => 'El campo :attribute no puede quedar vacío.',
    'gt' => [
        'array' => 'El campo :attribute debe tener más de :value elementos.',
        'file' => 'El campo :attribute debe pesar más de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
        'string' => 'El campo :attribute debe tener más de :value caracteres.',
    ],
    'gte' => [
        'array' => 'El campo :attribute debe tener :value elementos o más.',
        'file' => 'El campo :attribute debe pesar :value kilobytes o más.',
        'numeric' => 'El campo :attribute debe ser mayor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value caracteres o más.',
    ],
    'hex_color' => 'El campo :attribute debe ser un color hexadecimal válido.',
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'El valor del campo :attribute no está entre las opciones permitidas.',
    'in_array' => 'El campo :attribute debe existir en :other.',
    'in_array_keys' => 'El campo :attribute debe contener al menos una de estas claves: :values.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'ip' => 'El campo :attribute debe ser una dirección IP válida.',
    'ipv4' => 'El campo :attribute debe ser una dirección IPv4 válida.',
    'ipv6' => 'El campo :attribute debe ser una dirección IPv6 válida.',
    'json' => 'El campo :attribute debe ser un texto JSON válido.',
    'list' => 'El campo :attribute debe ser una lista.',
    'lowercase' => 'El campo :attribute debe ir en minúsculas.',
    'lt' => [
        'array' => 'El campo :attribute debe tener menos de :value elementos.',
        'file' => 'El campo :attribute debe pesar menos de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser menor que :value.',
        'string' => 'El campo :attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'El campo :attribute no puede tener más de :value elementos.',
        'file' => 'El campo :attribute debe pesar :value kilobytes o menos.',
        'numeric' => 'El campo :attribute debe ser menor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value caracteres o menos.',
    ],
    'mac_address' => 'El campo :attribute debe ser una dirección MAC válida.',
    'max' => [
        'array' => 'El campo :attribute no puede tener más de :max elementos.',
        'file' => 'El campo :attribute no puede pesar más de :max kilobytes.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
    ],
    'max_digits' => 'El campo :attribute no puede tener más de :max dígitos.',
    'mimes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El campo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'min_digits' => 'El campo :attribute debe tener al menos :min dígitos.',
    'missing' => 'El campo :attribute no debe enviarse.',
    'missing_if' => 'El campo :attribute no debe enviarse cuando :other es :value.',
    'missing_unless' => 'El campo :attribute no debe enviarse salvo que :other sea :value.',
    'missing_with' => 'El campo :attribute no debe enviarse junto con :values.',
    'missing_with_all' => 'El campo :attribute no debe enviarse junto con :values.',
    'multiple_of' => 'El campo :attribute debe ser múltiplo de :value.',
    'not_in' => 'El valor del campo :attribute no está permitido.',
    'not_regex' => 'El formato del campo :attribute no es válido.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'password' => [
        'letters' => 'El campo :attribute debe contener al menos una letra.',
        'mixed' => 'El campo :attribute debe contener al menos una mayúscula y una minúscula.',
        'numbers' => 'El campo :attribute debe contener al menos un número.',
        'symbols' => 'El campo :attribute debe contener al menos un símbolo.',
        'uncompromised' => 'El valor del campo :attribute apareció en una filtración de datos. Elija otro.',
    ],
    'present' => 'El campo :attribute debe enviarse.',
    'present_if' => 'El campo :attribute debe enviarse cuando :other es :value.',
    'present_unless' => 'El campo :attribute debe enviarse salvo que :other sea :value.',
    'present_with' => 'El campo :attribute debe enviarse junto con :values.',
    'present_with_all' => 'El campo :attribute debe enviarse junto con :values.',
    'prohibited' => 'El campo :attribute no se puede enviar en esta operación.',
    'prohibited_if' => 'El campo :attribute no se puede enviar cuando :other es :value.',
    'prohibited_if_accepted' => 'El campo :attribute no se puede enviar cuando se acepta :other.',
    'prohibited_if_declined' => 'El campo :attribute no se puede enviar cuando se rechaza :other.',
    'prohibited_unless' => 'El campo :attribute no se puede enviar salvo que :other sea :values.',
    'prohibits' => 'El campo :attribute impide enviar :other.',
    'regex' => 'El formato del campo :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_array_keys' => 'El campo :attribute debe contener entradas para: :values.',
    'required_if' => 'El campo :attribute es obligatorio cuando :other es :value.',
    'required_if_accepted' => 'El campo :attribute es obligatorio cuando se acepta :other.',
    'required_if_declined' => 'El campo :attribute es obligatorio cuando se rechaza :other.',
    'required_unless' => 'El campo :attribute es obligatorio salvo que :other sea :values.',
    'required_with' => 'El campo :attribute es obligatorio cuando se envía :values.',
    'required_with_all' => 'El campo :attribute es obligatorio cuando se envían :values.',
    'required_without' => 'El campo :attribute es obligatorio cuando no se envía :values.',
    'required_without_all' => 'El campo :attribute es obligatorio cuando no se envía ninguno de :values.',
    'same' => 'Los campos :attribute y :other deben coincidir.',
    'size' => [
        'array' => 'El campo :attribute debe contener :size elementos.',
        'file' => 'El campo :attribute debe pesar :size kilobytes.',
        'numeric' => 'El campo :attribute debe ser :size.',
        'string' => 'El campo :attribute debe tener :size caracteres.',
    ],
    'starts_with' => 'El campo :attribute debe empezar con uno de estos valores: :values.',
    'string' => 'El campo :attribute debe ser texto.',
    'timezone' => 'El campo :attribute debe ser una zona horaria válida.',
    'unique' => 'Ya existe un registro con ese valor en el campo :attribute.',
    'uploaded' => 'No se pudo subir el archivo del campo :attribute.',
    'uppercase' => 'El campo :attribute debe ir en mayúsculas.',
    'url' => 'El campo :attribute no es una URL válida.',
    'ulid' => 'El campo :attribute debe ser un ULID válido.',
    'uuid' => 'El campo :attribute debe ser un UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Mensajes por campo y regla
    |--------------------------------------------------------------------------
    |
    | Lo específico de una pantalla vive en el `messages()` de su FormRequest,
    | que es donde se lee junto a la regla. Aquí solo va lo que vale para todo
    | el sistema.
    |
    */

    'custom' => [
        'password' => [
            'confirmed' => 'Las contraseñas no coinciden.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nombres de los campos
    |--------------------------------------------------------------------------
    |
    | Con qué palabras se nombra cada campo dentro del mensaje, sin artículo.
    | Sin esto el mensaje decía «fecha_ingreso_institucion», que es el nombre de
    | la columna y no el de la pantalla.
    |
    */

    'attributes' => [
        // Identidad
        'cedula' => 'cédula',
        'nombre' => 'nombre',
        'nombres' => 'nombres',
        'segundo_nombre' => 'segundo nombre',
        'apellido' => 'apellido',
        'apellidos' => 'apellidos',
        'segundo_apellido' => 'segundo apellido',
        'fecha_nacimiento' => 'fecha de nacimiento',
        'genero' => 'género',
        'estado_civil' => 'estado civil',
        'tipo_sangre' => 'tipo de sangre',
        'es_extranjero' => 'condición de extranjero',
        'nacionalidad' => 'nacionalidad',
        'pais_origen' => 'país de origen',
        'provincia_nacimiento_id' => 'provincia de nacimiento',
        'canton_nacimiento_id' => 'cantón de nacimiento',
        'numero_papeleta_votacion' => 'papeleta de votación',
        'pasaporte_numero' => 'número de pasaporte',
        'pasaporte_vencimiento' => 'vencimiento del pasaporte',
        'parentesco' => 'parentesco',

        // Contacto
        'telefono_celular' => 'teléfono celular',
        'telefono_convencional' => 'teléfono convencional',
        'correo_personal' => 'correo personal',
        'email' => 'correo electrónico',
        'direccion_domicilio' => 'dirección domiciliaria',
        'codigo_medico' => 'código médico',

        // Vínculo laboral
        'regimen_laboral' => 'régimen laboral',
        'tipo_nombramiento' => 'tipo de nombramiento',
        'tipo_nombramiento_propuesto' => 'tipo de nombramiento propuesto',
        'numero_contrato' => 'número de contrato',
        'fecha_ingreso_institucion' => 'fecha de ingreso a la institución',
        'fecha_ingreso_sector_publico' => 'fecha de ingreso al sector público',
        'fecha_nombramiento' => 'fecha de nombramiento',
        'remuneracion' => 'remuneración',
        'remuneracion_propuesta' => 'remuneración propuesta',
        'partida_presupuestaria_id' => 'partida presupuestaria',
        'puesto_id' => 'puesto',
        'puesto_origen_id' => 'puesto de origen',
        'puesto_destino_id' => 'puesto de destino',
        'unidad_administrativa_id' => 'unidad administrativa',
        'unidad_origen_id' => 'unidad de origen',
        'unidad_destino_id' => 'unidad de destino',
        'servidor_id' => 'servidor',
        'puede_marcar' => 'marcación',
        'resolucion_numero' => 'número de resolución',

        // Fechas y estados comunes
        'fecha' => 'fecha',
        'fecha_inicio' => 'fecha de inicio',
        'fecha_fin' => 'fecha de fin',
        'fecha_efectiva' => 'fecha efectiva',
        'fecha_fin_propuesta' => 'fecha de fin propuesta',
        'estado' => 'estado',
        'activo' => 'estado',
        'tipo' => 'tipo',
        'codigo' => 'código',
        'descripcion' => 'descripción',
        'observacion' => 'observación',
        'observaciones' => 'observaciones',
        'motivo' => 'motivo',
        'documento' => 'documento',
        'archivo' => 'archivo',

        // Salud y dispensario
        'tiene_discapacidad' => 'condición de discapacidad',
        'tiene_enfermedad_catastrofica' => 'condición de enfermedad catastrófica',
        'tipo_discapacidad' => 'tipo de discapacidad',
        'porcentaje' => 'porcentaje',
        'numero_carnet_conadis' => 'carnet del CONADIS',
        'tipo_enfermedad' => 'tipo de enfermedad',
        'codigo_cie10' => 'código CIE-10',
        'fecha_diagnostico' => 'fecha de diagnóstico',
        'tipo_atencion' => 'tipo de atención',
        'tipo_evento' => 'tipo de evento',
        'consulta_medica_id' => 'consulta médica',
        'carga_familiar_id' => 'carga familiar',

        // Cuentas bancarias
        'entidad_financiera_id' => 'entidad financiera',
        'numero_cuenta' => 'número de cuenta',
        'tipo_cuenta' => 'tipo de cuenta',
        'proposito' => 'uso de la cuenta',

        // Sesión
        'password' => 'contraseña',
        'usuario_ti' => 'usuario',
    ],

];
