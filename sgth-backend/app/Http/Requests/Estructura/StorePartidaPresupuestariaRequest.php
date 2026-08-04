<?php

namespace App\Http\Requests\Estructura;

use Illuminate\Foundation\Http\FormRequest;

final class StorePartidaPresupuestariaRequest extends FormRequest
{
    /**
     * El acceso lo restringe el middleware de rol de la ruta
     * (admin-uath|admin-ti), igual que el resto de la gestión de catálogos
     * del módulo Estructura.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo'      => ['required', 'string', 'max:20', 'unique:partidas_presupuestarias,codigo'],
            'descripcion' => ['required', 'string', 'max:200'],
            'grupo_gasto' => ['nullable', 'string', 'max:100'],
            'activo'      => ['boolean'],
            // Disponibilidad presupuestaria verificada (Art. 105 LOSEP). No
            // se asume: default false en BD y aquí se declara explícitamente.
            'disponible'  => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required'      => 'El código de la partida es obligatorio.',
            'codigo.unique'        => 'Ya existe una partida presupuestaria con ese código.',
            'descripcion.required' => 'La descripción de la partida es obligatoria.',
        ];
    }
}
