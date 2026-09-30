<?php
namespace App\Http\Requests\Asistencia;

use App\Enums\MotivoVacacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreVacacionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'servidor_id'      => 'nullable|exists:servidores,id',
            'jefe_id'          => 'nullable|exists:servidores,id',
            'motivo'           => ['required', new Enum(MotivoVacacion::class)],
            'fecha_inicio'     => 'required|date',
            'fecha_fin'        => 'required|date|after_or_equal:fecha_inicio',
            'fecha_retorno'    => 'nullable|date|after:fecha_fin',
            'dias_solicitados' => 'required|integer|min:1',
            'tipo_dias'        => 'required|in:habiles,calendario',
            'observacion'      => 'nullable|string|max:500',
            'unidad_administrativa_id' => 'nullable|exists:unidades_administrativas,id',
            'persona_reemplaza_id' => 'nullable|exists:servidores,id',
            // Aquí se aceptaba `periodo_vacacion_id`, que el cliente podía
            // mandar y ningún servicio leía nunca: de qué períodos salen los
            // días lo decide el reparto al aprobar, y queda en
            // `vacacion_descuentos`.
        ];
    }
}
