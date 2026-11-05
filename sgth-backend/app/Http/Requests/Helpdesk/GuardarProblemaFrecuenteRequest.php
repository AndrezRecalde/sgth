<?php

namespace App\Http\Requests\Helpdesk;

use App\Enums\PrioridadTicket;
use App\Models\Helpdesk\ProblemaFrecuente;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Un problema frecuente: lo que ve el servidor (el nombre, que es también el
 * asunto del ticket) y lo que lleva el ticket. La categoría tiene que estar
 * activa; el nombre no se repite, sin distinguir mayúsculas. Al editar, cada
 * campo es opcional: activar o desactivar manda solo `activo`.
 */
class GuardarProblemaFrecuenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var ProblemaFrecuente|null $problema */
        $problema = $this->route('problema');
        $requerido = $problema ? 'sometimes' : 'required';

        return [
            'nombre' => [$requerido, 'string', 'min:5', 'max:80', function (string $atributo, mixed $valor, Closure $falla) use ($problema) {
                $repetido = is_string($valor) && ProblemaFrecuente::query()
                    ->whereRaw('lower(nombre) = lower(?)', [trim($valor)])
                    ->when($problema, fn ($q) => $q->whereKeyNot($problema->id))
                    ->exists();
                if ($repetido) {
                    $falla('Ya hay un problema frecuente con ese nombre.');
                }
            }],
            'categoria_id' => [$requerido, 'integer', Rule::exists('categorias_ticket', 'id')->where('activa', true)->whereNull('deleted_at')],
            'tipo_ticket'  => [$requerido, Rule::in(ProblemaFrecuente::TIPOS)],
            'prioridad'    => [$requerido, Rule::enum(PrioridadTicket::class)],
            'descripcion'  => [$requerido, 'string', 'min:10', 'max:1000'],
            'activo'       => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'categoria_id.exists' => 'Elija una categoría activa.',
            'tipo_ticket.in'      => 'Elija si es un incidente o una solicitud.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['nombre' => 'nombre', 'categoria_id' => 'categoría', 'tipo_ticket' => 'tipo', 'prioridad' => 'prioridad', 'descripcion' => 'descripción'];
    }
}
