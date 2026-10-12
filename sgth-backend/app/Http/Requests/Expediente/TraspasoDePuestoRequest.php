<?php

namespace App\Http\Requests\Expediente;

use App\Enums\CriterioTraspasoPuesto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El traspaso de un puesto a otra unidad (LOSEP Art. 37; Reglamento a la LOSEP
 * Art. 69; fase 7.1a). La vista previa pide solo el puesto y, si la hay, la
 * unidad; prepararlo pide además el informe técnico de la UATH, su criterio,
 * la explicación y la fecha en que rige.
 */
class TraspasoDePuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $previa = str_ends_with($this->path(), '/previsualizar');

        return [
            'puesto_id'           => ['required', 'integer', Rule::exists('puestos', 'id')->whereNull('deleted_at')],
            'unidad_destino_id'   => [$previa ? 'nullable' : 'required', 'integer', 'exists:unidades_administrativas,id'],
            'criterio'            => [$previa ? 'nullable' : 'required', Rule::enum(CriterioTraspasoPuesto::class)],
            'informe_uath_numero' => [$previa ? 'nullable' : 'required', 'string', 'max:100'],
            'descripcion'         => [$previa ? 'nullable' : 'required', 'string', 'min:10', 'max:1000'],
            'fecha_efectiva'      => [$previa ? 'nullable' : 'required', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'puesto_id'           => 'puesto',
            'unidad_destino_id'   => 'unidad de destino',
            'criterio'            => 'criterio del traspaso',
            'informe_uath_numero' => 'número del informe técnico de la UATH',
            'descripcion'         => 'explicación',
            'fecha_efectiva'      => 'fecha en que rige',
        ];
    }
}
