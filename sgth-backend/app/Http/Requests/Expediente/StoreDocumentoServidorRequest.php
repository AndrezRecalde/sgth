<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentoServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_documento' => 'required|string|in:cedula_identidad,papeleta_votacion,titulo_tercer_nivel,titulo_cuarto_nivel,certificado_trabajo_anterior,carnet_conadis,certificado_enfermedad_catastrofica,contrato_laboral,nombramiento,certificado_medico,otro',
            'archivo'        => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB máx
            'fecha_vencimiento' => 'nullable|date|after:today',
            'descripcion'    => 'nullable|string|max:255',
        ];
    }
}
