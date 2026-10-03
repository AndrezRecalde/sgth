<?php

namespace App\Http\Requests\Dispensario;

use App\Enums\EspecialidadAtencion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrosReporteDispensarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'desde'                    => ['required', 'date'],
            // Hasta un año: un reporte nominal de varios años no se revisa
            // en pantalla y es justo el que no debería salir de una vez.
            'hasta'                    => ['required', 'date', 'after_or_equal:desde', function ($atributo, $valor, $fallo) {
                if ($this->desde && strtotime($valor) - strtotime($this->desde) > 366 * 86400) {
                    $fallo('El período no puede pasar de un año.');
                }
            }],
            'profesional_id'           => ['nullable', 'integer'],
            'especialidad'             => ['nullable', Rule::enum(EspecialidadAtencion::class)],
            'tipo_paciente'            => ['nullable', 'in:servidor,familiar'],
            'unidad_administrativa_id' => ['nullable', 'integer'],
            'agrupacion'               => ['nullable', 'in:profesional,dia,unidad,diagnostico'],
        ];
    }

    public function attributes(): array
    {
        return [
            'desde'                    => 'desde',
            'hasta'                    => 'hasta',
            'profesional_id'           => 'profesional',
            'tipo_paciente'            => 'tipo de paciente',
            'unidad_administrativa_id' => 'unidad administrativa',
            'agrupacion'               => 'agrupar por',
        ];
    }
}
