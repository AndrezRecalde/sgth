<?php

namespace App\Http\Requests\InventarioTi;

use App\Enums\FichaTipoBien;
use App\Models\InventarioTi\Ubicacion;
use App\Rules\UnicoSinMayusculas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Un registro de los catálogos simples del inventario: tipos, subtipos, marcas
 * y ubicaciones. Cuál es lo dice el nombre de la ruta (`tipos.store`…), y
 * cada catálogo tiene sus campos.
 *
 * Los nombres no se repiten sin distinguir mayúsculas: en el Excel de TIC
 * «DELL» y «DELL » eran dos marcas.
 */
class StoreCatalogoInventarioRequest extends FormRequest
{
    /** El parámetro de ruta de cada catálogo, para ignorar el propio al editar. */
    private const PARAMETROS = ['tipos' => 'tipo', 'subtipos' => 'subtipo', 'marcas' => 'marca', 'ubicaciones' => 'ubicacion'];

    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    protected function prepareForValidation(): void
    {
        foreach (['nombre', 'sede', 'piso', 'descripcion'] as $campo) {
            if (is_string($this->input($campo))) {
                $valor = preg_replace('/\s+/', ' ', trim($this->input($campo)));
                $this->merge([$campo => $valor === '' ? null : $valor]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->reglas(null, 'required');
    }

    protected function catalogo(): string
    {
        return explode('.', (string) $this->route()?->getName())[0];
    }

    protected function idPropio(): ?int
    {
        $modelo = $this->route(self::PARAMETROS[$this->catalogo()] ?? '');

        return $modelo?->id;
    }

    /** @return array<string, mixed> */
    protected function reglas(?int $ignorar, string $presencia): array
    {
        return match ($this->catalogo()) {
            'tipos' => [
                'nombre'          => [$presencia, 'string', 'max:100', new UnicoSinMayusculas('tipos_bien', 'nombre', $ignorar)],
                'anios_vida_util' => ['sometimes', 'integer', 'min:0', 'max:30'],
                'ficha'           => ['sometimes', Rule::enum(FichaTipoBien::class)],
            ],
            'subtipos' => [
                'tipo_bien_id' => [$presencia, 'integer', Rule::exists('tipos_bien', 'id')->whereNull('deleted_at')],
                'nombre'       => [$presencia, 'string', 'max:100', new UnicoSinMayusculas(
                    'subtipos_bien', 'nombre', $ignorar,
                    ['tipo_bien_id' => $this->input('tipo_bien_id', $this->route('subtipo')?->tipo_bien_id)],
                )],
            ],
            'marcas' => [
                'nombre' => [$presencia, 'string', 'max:100', new UnicoSinMayusculas('marcas', 'nombre', $ignorar)],
            ],
            'ubicaciones' => [
                'sede'        => [$presencia, 'string', 'max:100'],
                'piso'        => ['sometimes', 'nullable', 'string', 'max:30'],
                'descripcion' => ['sometimes', 'nullable', 'string', 'max:200'],
            ],
            default => [],
        };
    }

    /** Sede y piso juntos no se repiten, lleguen los dos o uno solo. */
    public function after(): array
    {
        if ($this->catalogo() !== 'ubicaciones') {
            return [];
        }

        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->ubicacionRepetida($this->idPropio())) {
                $validator->errors()->add('sede', 'Esa sede ya tiene registrado ese piso.');
            }
        }];
    }

    private function ubicacionRepetida(?int $ignorar): bool
    {
        $actual = $this->route('ubicacion');
        $sede = $this->input('sede', $actual?->sede);
        $piso = $this->exists('piso') ? $this->input('piso') : $actual?->piso;

        return Ubicacion::query()
            ->whereRaw('lower(sede) = lower(?)', [$sede])
            ->whereRaw("lower(coalesce(piso, '')) = lower(?)", [$piso ?? ''])
            ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar))
            ->exists();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'anios_vida_util' => 'años de vida útil',
            'tipo_bien_id'    => 'tipo de equipo',
        ];
    }
}
