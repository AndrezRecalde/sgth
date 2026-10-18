<?php

namespace App\Services\Comunicados;

use App\Contracts\Comunicados\DestinatariosComunicadoServiceInterface;
use App\Enums\AudienciaComunicado;
use App\Models\Comunicados\Comunicado;
use App\Models\User;
use App\Services\Estructura\ArbolUnidades;
use Illuminate\Database\Eloquent\Builder;

/**
 * A quién llega una publicación (docs/diseno-noticias-comunicados.md, 3.3).
 *
 * Se calcula al leer, no se congela al publicar: quien ingresa la semana
 * siguiente ve lo vigente de su unidad. Lo único congelado son las
 * dependencias de cada unidad elegida, que ComunicadoService expande al
 * publicar.
 *
 * La unidad y el régimen se leen de la ficha del servidor
 * (`servidores.unidad_administrativa_id` y `regimen_laboral`). Una cuenta sin
 * servidor, o con la ficha inactiva, solo recibe lo dirigido a todos.
 */
final class DestinatariosComunicadoService implements DestinatariosComunicadoServiceInterface
{
    public function usuarios(AudienciaComunicado $audiencia, array $unidadIds, array $regimenes): Builder
    {
        $query = User::query()->where('activo', true);

        if ($audiencia === AudienciaComunicado::TODOS) {
            return $query;
        }

        // Segmentada sin unidades ni regímenes no es «todos»: no es nadie. La
        // publicación lo rechaza antes; aquí, para que el conteo diga cero.
        if ($unidadIds === [] && $regimenes === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('servidor', function (Builder $servidor) use ($unidadIds, $regimenes) {
            $servidor->where('estado', true);

            if ($unidadIds !== []) {
                $servidor->whereIn('unidad_administrativa_id', $unidadIds);
            }

            if ($regimenes !== []) {
                $servidor->whereIn('regimen_laboral', $regimenes);
            }
        });
    }

    public function contar(Comunicado $comunicado): int
    {
        $unidadIds = $comunicado->esBorrador()
            ? $this->expandir($this->seleccion($comunicado))
            : $comunicado->unidades()->pluck('unidades_administrativas.id')->map(fn ($id) => (int) $id)->all();

        return $this->usuarios($comunicado->audiencia, $unidadIds, $comunicado->regimenes ?? [])->count();
    }

    public function contarSeleccion(AudienciaComunicado $audiencia, array $unidades, array $regimenes): int
    {
        return $this->usuarios($audiencia, $this->expandir($unidades), array_values($regimenes))->count();
    }

    public function expandir(array $unidades): array
    {
        $ids = [];

        foreach ($unidades as $unidad) {
            $id = (int) $unidad['id'];
            array_push($ids, ...(! empty($unidad['incluye_dependencias'])
                ? ArbolUnidades::conDescendientes($id)
                : [$id]));
        }

        return array_values(array_unique($ids));
    }

    /** @return list<array{id: int, incluye_dependencias: bool}> */
    private function seleccion(Comunicado $comunicado): array
    {
        return $comunicado->unidadesSeleccionadas()->get()
            ->map(fn ($unidad) => [
                'id'                   => (int) $unidad->id,
                'incluye_dependencias' => (bool) $unidad->pivot->incluye_dependencias,
            ])
            ->all();
    }
}
