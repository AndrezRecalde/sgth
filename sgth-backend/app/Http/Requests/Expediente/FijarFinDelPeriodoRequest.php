<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El fin del período de una autoridad electa que no lo tiene (fase 4.1). Que no
 * pase de cuatro años lo valida AutoridadElectaService, que conoce el inicio.
 */
class FijarFinDelPeriodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_fin' => ['required', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'fecha_fin' => 'fin del período',
        ];
    }
}
