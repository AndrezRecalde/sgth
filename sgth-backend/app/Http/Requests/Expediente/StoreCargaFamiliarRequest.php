<?php

namespace App\Http\Requests\Expediente;

use App\Enums\TipoParentesco;
use App\Models\Expediente\CargaFamiliar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreCargaFamiliarRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'cedula'                        => $this->reglasDeCedula(),
            'apellidos'                     => ['required', 'string', 'max:100'],
            'nombres'                       => ['required', 'string', 'max:100'],
            'parentesco'                    => ['required', new Enum(TipoParentesco::class)],
            'fecha_nacimiento'              => ['required', 'date', 'before:today'],
            // El sexo, con los valores de `servidores.genero`. Obligatorio al
            // registrar o editar: los familiares antiguos lo completan así.
            'genero'                        => ['required', 'in:masculino,femenino'],
            'persona_con_discapacidad'      => ['required', 'boolean'],
            'posee_enfermedad_catastrofica' => ['required', 'boolean'],
            'observaciones'                 => ['nullable', 'string'],
        ];
    }

    /**
     * La cédula identifica al familiar en el Dispensario: su historia clínica
     * se numera con ella. Por eso, una vez registrada, no cambia.
     *
     * Hasta el 2026-10-03 la regla `unique` no excluía la propia fila, así que
     * ningún familiar se podía editar: la cédula chocaba consigo misma.
     */
    private function reglasDeCedula(): array
    {
        $id = $this->route('id');

        // Alta. Un familiar borrado no cuenta: volver a registrarlo lo
        // recupera con su misma fila (ver el controlador).
        if ($id === null) {
            return [
                'required', 'digits:10',
                Rule::unique('cargas_familiares', 'cedula')->withoutTrashed(),
            ];
        }

        $cedulaActual = CargaFamiliar::whereKey($id)->value('cedula');

        // Edición de un familiar con cédula: se puede reenviar, no cambiar.
        if ($cedulaActual !== null) {
            return ['sometimes', Rule::in([$cedulaActual])];
        }

        // Familiar anterior a la columna `cedula`: la escribe al editarlo.
        // Aquí el borrado sí cuenta, porque esta fila no se recupera de él y
        // el índice único de la base de datos lo incluye.
        return [
            'required', 'digits:10',
            Rule::unique('cargas_familiares', 'cedula'),
        ];
    }

    public function messages(): array
    {
        return [
            'cedula.required' => 'La cédula del familiar es obligatoria.',
            'cedula.digits'   => 'La cédula debe tener 10 dígitos.',
            'cedula.unique'   => 'Esta cédula ya está registrada como carga familiar.',
            'cedula.in'       => 'La cédula de un familiar no se modifica una vez registrada.',
            'genero.required' => 'Indique el sexo del familiar.',
            'genero.in'       => 'El sexo debe ser masculino o femenino.',
        ];
    }
}
