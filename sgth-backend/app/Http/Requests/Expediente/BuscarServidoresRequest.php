<?php

namespace App\Http\Requests\Expediente;

use App\Enums\Permiso;
use Illuminate\Foundation\Http\FormRequest;

/** Lo que escribe quien busca a un servidor en un formulario. */
class BuscarServidoresRequest extends FormRequest
{
    private const TOPE_DE_LA_BUSQUEDA = 20;

    private const TOPE_DE_UNA_LISTA = 500;

    /**
     * Buscar por texto lo resuelve el permiso de la ruta. Listar una unidad o a
     * los jefes es solo para quien registra a nombre de otro, que elige de ahí
     * al servidor, a quien firma y a quien reemplaza: TIC y el auditor buscan a
     * una persona y no tienen por qué sacar la lista de una dirección.
     */
    public function authorize(): bool
    {
        return ! $this->pideUnaLista() || $this->user()->canAny([
            Permiso::REGISTRAR_PERMISOS_SERVIDORES->value,
            Permiso::GESTIONAR_VACACIONES->value,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tope = $this->pideUnaLista() ? self::TOPE_DE_UNA_LISTA : self::TOPE_DE_LA_BUSQUEDA;

        return [
            // Desde dos letras, como el buscador: con una sola, media
            // institución coincide. Sin texto solo se pide una lista.
            'search'                   => ['required_without_all:unidad_administrativa_id,es_jefe', 'nullable', 'string', 'min:2', 'max:100'],
            'unidad_administrativa_id' => ['nullable', 'integer'],
            // Solo «sí»: `es_jefe=0` sería casi toda la institución. Con
            // `sometimes`: `accepted` se evalúa aunque el campo no venga.
            'es_jefe'                  => ['sometimes', 'accepted'],
            'en_funciones'             => ['nullable', 'boolean'],
            'per_page'                 => ['nullable', 'integer', 'min:1', 'max:'.$tope],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'search'                   => 'búsqueda',
            'unidad_administrativa_id' => 'unidad administrativa',
            'es_jefe'                  => 'jefatura',
            'en_funciones'             => 'en funciones',
            'per_page'                 => 'cantidad de resultados',
        ];
    }

    private function pideUnaLista(): bool
    {
        return $this->filled('unidad_administrativa_id') || $this->filled('es_jefe');
    }
}
