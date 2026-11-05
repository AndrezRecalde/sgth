<?php

namespace App\Http\Requests\Helpdesk;

use App\Enums\PrioridadTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Abrir un ticket (fase 3.1). Desde el Portal, a nombre de quien lo abre;
 * TIC lo registra a nombre de otro con `servidor_id` y puede poner la
 * prioridad. Que el equipo sea suyo lo comprueba el servicio.
 *
 * Con un problema frecuente (2026-11-05), la categoría, el asunto, la
 * descripción y el tipo salen de él: solo se manda, si se quiere, un
 * comentario. Sin él, es el formulario libre de siempre.
 */
final class CrearTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A nombre de otro, solo quien atiende tickets.
        return ! $this->filled('servidor_id') || (bool) $this->user()?->can('registrarANombre', \App\Models\Helpdesk\Ticket::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['asunto', 'descripcion', 'comentario'] as $campo) {
            if (is_string($this->input($campo))) {
                $this->merge([$campo => trim($this->input($campo))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $adjuntos = config('helpdesk.adjuntos');

        return [
            'servidor_id'         => ['nullable', 'integer', Rule::exists('servidores', 'id')],
            'problema_frecuente_id' => ['nullable', 'integer', Rule::exists('problemas_frecuentes', 'id')->where('activo', true)],
            'comentario'          => ['nullable', 'string', 'max:2000'],
            'categoria_id'        => ['required_without:problema_frecuente_id', 'nullable', 'integer', Rule::exists('categorias_ticket', 'id')->where('activa', true)->whereNull('deleted_at')],
            'tipo_ticket'         => ['nullable', Rule::in(['incidente', 'solicitud'])],
            'prioridad'           => ['nullable', Rule::enum(PrioridadTicket::class)],
            'asunto'              => ['required_without:problema_frecuente_id', 'nullable', 'string', 'min:5', 'max:200'],
            'descripcion'         => ['required_without:problema_frecuente_id', 'nullable', 'string', 'min:10', 'max:5000'],
            'bien_informatico_id' => ['nullable', 'integer', Rule::exists('bienes_informaticos', 'id')->whereNull('deleted_at')],
            'archivo'             => ['nullable', 'file', 'max:' . $adjuntos['max_kb'], 'mimes:' . implode(',', $adjuntos['tipos'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'problema_frecuente_id.exists' => 'Ese problema ya no se ofrece: elija otro o «Otro problema».',
            'categoria_id.required_without' => 'Elija de qué se trata.',
            'asunto.required_without'       => 'Resuma el problema.',
            'descripcion.required_without'  => 'Cuéntenos qué pasa.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'servidor_id'         => 'servidor',
            'problema_frecuente_id' => 'problema',
            'comentario'          => 'comentario',
            'categoria_id'        => 'categoría',
            'tipo_ticket'         => 'tipo',
            'asunto'              => 'asunto',
            'descripcion'         => 'descripción',
            'bien_informatico_id' => 'equipo',
            'archivo'             => 'archivo adjunto',
        ];
    }
}
