<?php

namespace App\Http\Requests\Expediente;

use App\Enums\CausaLicencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Una cláusula de licencia del contrato colectivo (fase 5.3): amplía una causa
 * de la ley o crea una con su nombre. Que mejore la ley y que no se repita lo
 * decide `ContratoColectivoService`, que conoce el contrato.
 */
class GuardarClausulaDeLicenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La ruta la limita al director de Talento Humano, como el contrato.
        return true;
    }

    public function attributes(): array
    {
        return [
            'clausula'      => 'cláusula',
            'causa'         => 'causa que amplía',
            'alcance'       => 'caso',
            'nombre'        => 'nombre de la licencia',
            'dias'          => 'días por licencia',
            'dias_por_anio' => 'días por año',
            'observacion'   => 'observación',
        ];
    }

    public function rules(): array
    {
        $legales = array_map(fn (CausaLicencia $c) => $c->value, CausaLicencia::legales());

        return [
            'clausula'      => ['required', 'string', 'max:60'],
            'causa'         => ['nullable', Rule::in($legales)],
            'alcance'       => ['nullable', 'string', 'max:40'],
            'nombre'        => ['nullable', 'required_without:causa', 'prohibited_unless:causa,null', 'string', 'max:150'],
            'dias'          => ['required', 'integer', 'min:1', 'max:365'],
            'dias_por_anio' => ['nullable', 'integer', 'gte:dias', 'max:366'],
            'observacion'   => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required_without'   => 'La licencia que la ley no tiene lleva el nombre que le da el contrato.',
            'nombre.prohibited_unless'  => 'La cláusula que amplía una causa de la ley no lleva nombre propio.',
            'dias_por_anio.gte'         => 'El tope por año no puede ser menor que los días de una licencia.',
        ];
    }
}
