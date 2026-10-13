<?php

namespace App\Http\Requests\Helpdesk;

use Illuminate\Foundation\Http\FormRequest;

/** Un mensaje en la conversación de un ticket, con un archivo si hace falta. */
final class ComentarTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Quién puede escribir lo decide la policy en el controlador.
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('comentario'))) {
            $this->merge(['comentario' => trim($this->input('comentario'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $adjuntos = config('helpdesk.adjuntos');

        return [
            'comentario' => ['required', 'string', 'max:2000'],
            'es_interno' => ['nullable', 'boolean'],
            'archivo'    => ['nullable', 'file', 'max:' . $adjuntos['max_kb'], 'mimes:' . implode(',', $adjuntos['tipos'])],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['comentario' => 'mensaje', 'es_interno' => 'nota interna', 'archivo' => 'archivo adjunto'];
    }
}
