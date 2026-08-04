<?php

namespace App\Http\Requests\Expediente;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\TipoMovimientoPersonal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CorregirMovimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_movimiento'   => ['sometimes', new Enum(TipoMovimientoPersonal::class)],
            'categoria'         => ['sometimes', 'nullable', new Enum(CategoriaEventoVinculo::class)],
            'descripcion'       => ['sometimes', 'string'],
            'fecha_efectiva'    => ['sometimes', 'date'],
            'fecha_inicio'      => ['sometimes', 'nullable', 'date'],
            'fecha_fin'         => ['sometimes', 'nullable', 'date'],
            'unidad_origen_id'  => ['sometimes', 'nullable', 'exists:unidades_administrativas,id'],
            'unidad_destino_id' => ['sometimes', 'nullable', 'exists:unidades_administrativas,id'],
            'puesto_origen_id'  => ['sometimes', 'nullable', 'exists:puestos,id'],
            'puesto_destino_id' => ['sometimes', 'nullable', 'exists:puestos,id'],
            'resolucion_numero' => ['sometimes', 'nullable', 'string', 'max:255'],
            'observacion'       => ['sometimes', 'nullable', 'string'],
        ];
    }
}
