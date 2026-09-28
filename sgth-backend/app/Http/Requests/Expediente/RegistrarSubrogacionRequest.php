<?php

namespace App\Http\Requests\Expediente;

use App\Enums\MotivoSubrogacion;
use App\Enums\TipoSubrogacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Datos de una subrogación o encargo nuevos.
 *
 * Sale del controlador, donde vivía como un `$request->validate()` con el
 * comentario «Aquí validamos básico» — y lo básico dejaba pasar un `motivo`
 * cualquiera: la columna se castea a `MotivoSubrogacion`, así que un valor
 * fuera de lista reventaba en el cast de Eloquent y el API respondía 500 en vez
 * de 422. `tipo`, al lado, sí estaba acotado.
 *
 * Las reglas de negocio —quién puede ser titular, traslapes, la figura que
 * corresponde según el puesto— siguen en SubrogacionService: dependen del
 * estado de la base, no del formato de la petición.
 */
class RegistrarSubrogacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización la resuelve el controlador contra SubrogacionPolicy.
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo'                     => ['required', new Enum(TipoSubrogacion::class)],
            'servidor_subrogante_id'   => ['required', 'integer', 'exists:servidores,id'],
            'servidor_subrogado_id'    => ['nullable', 'integer', 'exists:servidores,id'],
            'unidad_administrativa_id' => ['required', 'integer', 'exists:unidades_administrativas,id'],
            'puesto_subrogado_id'      => ['required', 'integer', 'exists:puestos,id'],
            'fecha_inicio'             => ['required', 'date'],
            'fecha_fin'                => ['required', 'date', 'after:fecha_inicio'],
            'motivo'                   => ['required', new Enum(MotivoSubrogacion::class)],
            'resolucion_numero'        => ['nullable', 'string', 'max:100'],
            'observacion'              => ['nullable', 'string', 'max:1000'],
        ];
    }
}
