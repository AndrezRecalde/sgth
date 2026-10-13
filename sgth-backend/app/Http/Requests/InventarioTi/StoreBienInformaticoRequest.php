<?php

namespace App\Http\Requests\InventarioTi;

use App\Enums\CondicionFisicaBien;
use App\Enums\EstadoBien;
use App\Rules\UnicoSinMayusculas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La ficha de un equipo (docs/diseno-subsistema-tic.md, 4.2).
 *
 * El código institucional es obligatorio: todo equipo entra por Activos Fijos,
 * que se lo asigna (D2). La serie es opcional, porque hay equipos «S/N», y si
 * se da no puede repetirse.
 *
 * `codigo_qr` y la fecha de fin de vida útil no se aceptan: los calcula el
 * servicio.
 */
class StoreBienInformaticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    protected function prepareForValidation(): void
    {
        foreach (['codigo_institucional', 'codigo_anterior', 'numero_serie', 'modelo', 'nombre_red', 'usuario_dominio', 'direccion_ip'] as $campo) {
            if (is_string($this->input($campo))) {
                $valor = trim($this->input($campo));
                $this->merge([$campo => $valor === '' ? null : $valor]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->reglas(null, 'required');
    }

    /** @return array<string, mixed> */
    protected function reglas(?int $ignorar, string $presencia): array
    {
        $vivo = fn (string $tabla) => Rule::exists($tabla, 'id')->whereNull('deleted_at');

        return [
            'codigo_institucional' => [$presencia, 'string', 'max:50', new UnicoSinMayusculas('bienes_informaticos', 'codigo_institucional', $ignorar)],
            'codigo_anterior'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'numero_serie'         => ['sometimes', 'nullable', 'string', 'max:100', new UnicoSinMayusculas('bienes_informaticos', 'numero_serie', $ignorar)],
            'tipo_bien_id'         => [$presencia, 'integer', $vivo('tipos_bien')],
            'subtipo_bien_id'      => ['sometimes', 'nullable', 'integer', $vivo('subtipos_bien')],
            'marca_id'             => [$presencia, 'integer', $vivo('marcas')],
            'modelo'               => ['sometimes', 'nullable', 'string', 'max:100'],
            'origen_bien_id'       => ['sometimes', 'nullable', 'integer', $vivo('origenes_bien')],
            'fecha_compra'         => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'ubicacion_id'         => ['sometimes', 'nullable', 'integer', $vivo('ubicaciones')],
            'unidad_administrativa_id' => ['sometimes', 'nullable', 'integer', 'exists:unidades_administrativas,id'],
            'nombre_red'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'usuario_dominio'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'direccion_ip'         => ['sometimes', 'nullable', 'ip'],
            'bien_padre_id'        => ['sometimes', 'nullable', 'integer', $vivo('bienes_informaticos')],
            'contrato_vigencia_id' => ['sometimes', 'nullable', 'integer', $vivo('contratos_vigencia')],
            'prestable'            => ['sometimes', 'boolean'],
            'estado_operativo'     => ['sometimes', Rule::enum(EstadoBien::class)->except([EstadoBien::DADO_DE_BAJA])],
            'condicion_fisica'     => ['sometimes', Rule::enum(CondicionFisicaBien::class)],
            'caracteristicas_tecnicas' => ['sometimes', 'nullable', 'array'],
            'observaciones'        => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'estado_operativo.enum' => 'La baja de un equipo se registra en «bajas», con su informe técnico.',
            'fecha_compra.before_or_equal' => 'La fecha de compra no puede ser futura.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'codigo_institucional' => 'código institucional',
            'codigo_anterior'      => 'código anterior',
            'numero_serie'         => 'número de serie',
            'tipo_bien_id'         => 'tipo de equipo',
            'subtipo_bien_id'      => 'subtipo',
            'marca_id'             => 'marca',
            'origen_bien_id'       => 'origen',
            'fecha_compra'         => 'fecha de compra',
            'ubicacion_id'         => 'ubicación',
            'unidad_administrativa_id' => 'unidad',
            'nombre_red'           => 'nombre en la red',
            'usuario_dominio'      => 'usuario del dominio',
            'direccion_ip'         => 'dirección IP',
            'bien_padre_id'        => 'equipo principal',
            'contrato_vigencia_id' => 'contrato de vigencia',
            'estado_operativo'     => 'estado',
            'condicion_fisica'     => 'condición física',
        ];
    }
}
