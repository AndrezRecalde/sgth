<?php

namespace App\Http\Requests\Expediente;

use App\Enums\TipoMovimientoPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La revisión de la clasificación de uno o varios puestos ocupados (LOSEP
 * Arts. 61 y 62; fase 7.1b). La vista previa pide la causal y los puestos, con
 * su grado y su denominación nuevos; prepararla pide además la resolución que
 * la aprueba, la explicación y la fecha. El dictamen, si sube la remuneración:
 * eso lo mira el servicio, que sabe cuánto.
 */
class RevisionDeClasificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $previa = str_ends_with($this->path(), '/previsualizar');
        $causales = array_map(
            fn (SubtipoMovimientoPersonal $s) => $s->value,
            TipoMovimientoPersonal::REVISION_CLASIFICACION->subtiposPermitidos(),
        );

        return [
            'causal'                         => ['required', Rule::in($causales)],
            'puestos'                        => ['required', 'array', 'min:1', 'max:100'],
            'puestos.*.puesto_id'            => ['required', 'integer', 'distinct', Rule::exists('puestos', 'id')->whereNull('deleted_at')],
            'puestos.*.grupo_ocupacional_id' => ['nullable', 'integer', 'exists:grupos_ocupacionales,id'],
            'puestos.*.cargo_id'             => ['nullable', 'integer', 'exists:cargos,id'],
            'documento_origen'               => [$previa ? 'nullable' : 'required', 'string', 'max:150'],
            'dictamen_presupuestario_ref'    => ['nullable', 'string', 'max:100'],
            'descripcion'                    => [$previa ? 'nullable' : 'required', 'string', 'min:10', 'max:1000'],
            'fecha_efectiva'                 => [$previa ? 'nullable' : 'required', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'causal'                         => 'tipo de revisión',
            'puestos'                        => 'puestos',
            'puestos.*.puesto_id'            => 'puesto',
            'puestos.*.grupo_ocupacional_id' => 'grupo ocupacional nuevo',
            'puestos.*.cargo_id'             => 'denominación nueva',
            'documento_origen'               => 'resolución que la aprueba',
            'dictamen_presupuestario_ref'    => 'dictamen presupuestario',
            'descripcion'                    => 'explicación',
            'fecha_efectiva'                 => 'fecha en que rige',
        ];
    }
}
