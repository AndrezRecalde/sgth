<?php

namespace App\Http\Requests\Sgd;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentoInstitucionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Se asume que el usuario autenticado puede subir documentos
        // La política real podría restringir si el trámite está cerrado.
        return true;
    }

    public function rules(): array
    {
        return [
            'tramite_id'      => ['required', 'integer', 'exists:tramites,id'],
            'nombre'          => ['required', 'string', 'max:255'],
            'archivo'         => ['required', 'file', 'mimes:pdf,doc,docx,jpg,png', 'max:10240'], // Max 10MB
            'es_confidencial' => ['sometimes', 'boolean'],
            'publicar_lotaip' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tramite_id.required'  => 'El identificador del trámite es obligatorio.',
            'tramite_id.exists'    => 'El trámite especificado no existe.',
            'nombre.required'      => 'El nombre del documento es obligatorio.',
            'archivo.required'     => 'Debe adjuntar un archivo válido.',
            'archivo.file'         => 'El adjunto debe ser un archivo.',
            'archivo.mimes'        => 'Solo se permiten archivos PDF, DOC, DOCX, JPG o PNG.',
            'archivo.max'          => 'El archivo no puede exceder los 10 Megabytes.',
        ];
    }
}
