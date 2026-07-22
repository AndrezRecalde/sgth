<?php

namespace App\Http\Requests\Expediente;

use App\Enums\TipoMovimientoPersonal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreMovimientoPersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_movimiento'   => ['required', new Enum(TipoMovimientoPersonal::class)],
            'descripcion'       => 'required|string|max:1000',
            'fecha_efectiva'    => 'required|date',
            'fecha_inicio'      => 'nullable|date',
            'fecha_fin'         => 'nullable|date|after:fecha_inicio',
            'unidad_origen_id'  => 'nullable|exists:unidades_administrativas,id',
            'unidad_destino_id' => 'nullable|exists:unidades_administrativas,id',
            'puesto_origen_id'  => 'nullable|exists:puestos,id',
            'puesto_destino_id' => 'nullable|exists:puestos,id',
            'resolucion_numero' => 'nullable|string|max:100',
            'observacion'       => 'nullable|string|max:1000',
            'codigo'            => 'nullable|string|max:30',
            'lugar_trabajo'     => 'nullable|string|max:255',
            'caucionado'        => 'nullable|boolean',
            'caucion_numero'    => 'nullable|string|max:100',
            'caucion_fecha'     => 'nullable|date',
        ];
    }
}
