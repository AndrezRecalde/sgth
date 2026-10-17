<?php

namespace App\Http\Requests\Autoservicio;

use App\Http\Requests\Expediente\StoreHistorialAcademicoRequest;

/**
 * Un título o curso que el servidor registra desde «Mi perfil»: los mismos
 * datos que pide Talento Humano en el expediente, y además el PDF con el que
 * se valida.
 */
class StoreMiFormacionRequest extends StoreHistorialAcademicoRequest
{
    public const MAX_KB = 10240;

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'documento' => ['required', 'file', 'mimes:pdf', 'max:'.self::MAX_KB],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'documento.required' => 'Adjunte el título, el certificado del curso o el registro de la SENESCYT en PDF: con él lo valida Talento Humano.',
            'documento.mimes'    => 'El documento tiene que ser un PDF.',
            'documento.max'      => 'El PDF no puede pasar de 10 MB.',
        ];
    }
}
