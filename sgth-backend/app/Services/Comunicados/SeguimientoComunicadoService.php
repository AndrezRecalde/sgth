<?php

namespace App\Services\Comunicados;

use App\Contracts\Comunicados\DestinatariosComunicadoServiceInterface;
use App\Contracts\Comunicados\SeguimientoComunicadoServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Comunicados\Comunicado;
use App\Services\Estructura\ArbolUnidades;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * El seguimiento de una publicación (docs/diseno-noticias-comunicados.md, 6.4).
 *
 * La base son los destinatarios de hoy —`DestinatariosComunicadoService::usuarios`,
 * con las dependencias congeladas al publicar— y, de cada uno, su lectura. Así
 * la tabla, las cifras y el Excel dicen lo mismo que el Portal: alguien que
 * cambió de dirección después de leer ya no cuenta, y quien ingresó después
 * de publicado aparece como pendiente.
 */
final class SeguimientoComunicadoService implements SeguimientoComunicadoServiceInterface
{
    private const POR_PAGINA = 15;
    private const MAXIMO_POR_PAGINA = 100;

    private const COLUMNAS_BUSQUEDA = [
        'ficha.cedula', 'ficha.nombre', 'ficha.segundo_nombre', 'ficha.apellido', 'ficha.segundo_apellido', 'users.email',
    ];

    public function __construct(private readonly DestinatariosComunicadoServiceInterface $destinatarios) {}

    public function resumen(Comunicado $comunicado): array
    {
        $base = $this->base($comunicado);
        $total = (clone $base)->count();
        $leidos = (clone $base)->whereNotNull('lectura.leido_at')->count();
        $confirmados = (clone $base)->whereNotNull('lectura.confirmado_at')->count();

        return [
            'destinatarios' => $total,
            'leidos'        => $leidos,
            'confirmados'   => $confirmados,
            'pendientes'    => $total - ($comunicado->requiere_confirmacion ? $confirmados : $leidos),
        ];
    }

    public function listar(Comunicado $comunicado, array $filtros): LengthAwarePaginator
    {
        $query = $this->conColumnas($this->base($comunicado));

        match ($filtros['estado'] ?? null) {
            'sin_leer'   => $query->whereNull('lectura.leido_at'),
            'leido'      => $query->whereNotNull('lectura.leido_at')->whereNull('lectura.confirmado_at'),
            'confirmado' => $query->whereNotNull('lectura.confirmado_at'),
            'pendiente'  => $this->soloPendientes($query, $comunicado),
            default      => null,
        };

        $this->deLaUnidad($query, $filtros['unidad_administrativa_id'] ?? null);

        foreach (preg_split('/\s+/', trim((string) ($filtros['buscar'] ?? ''))) as $palabra) {
            if ($palabra === '') {
                continue;
            }
            $query->where(function (Builder $q) use ($palabra) {
                foreach (self::COLUMNAS_BUSQUEDA as $columna) {
                    $q->orWhere($columna, 'ilike', "%{$palabra}%");
                }
            });
        }

        $porPagina = min(max((int) ($filtros['per_page'] ?? self::POR_PAGINA), 1), self::MAXIMO_POR_PAGINA);

        // El id desempata: dos homónimos no se repiten ni se pierden entre páginas.
        $pagina = $query->orderBy('ficha.apellido')->orderBy('ficha.nombre')->orderBy('users.id')->paginate($porPagina);
        $pagina->setCollection($pagina->getCollection()->map(fn ($fila) => $this->fila($fila)));

        return $pagina;
    }

    public function pendientes(Comunicado $comunicado, ?int $unidadId = null): Collection
    {
        $query = $this->soloPendientes($this->conColumnas($this->base($comunicado)), $comunicado);
        $this->deLaUnidad($query, $unidadId);

        return $query->orderBy('unidad.nombre')->orderBy('ficha.apellido')->orderBy('ficha.nombre')->orderBy('users.id')
            ->get()
            ->map(fn ($fila) => $this->fila($fila));
    }

    /**
     * Los destinatarios de hoy con su lectura de esta publicación, si la hay.
     * Va como consulta de base, sin modelos: un `User` calcularía su nombre
     * cargando el servidor de cada fila.
     */
    private function base(Comunicado $comunicado): Builder
    {
        if ($comunicado->esBorrador()) {
            throw new ReglaNegocioException('Un borrador todavía no tiene lecturas: publíquelo primero.');
        }

        $unidadIds = $comunicado->unidades()->pluck('unidades_administrativas.id')->map(fn ($id) => (int) $id)->all();

        return $this->destinatarios
            ->usuarios($comunicado->audiencia, $unidadIds, $comunicado->regimenes ?? [])
            ->toBase()
            ->leftJoin('comunicado_lecturas as lectura', fn ($join) => $join
                ->on('lectura.user_id', '=', 'users.id')
                ->where('lectura.comunicado_id', '=', $comunicado->id))
            ->leftJoin('servidores as ficha', 'ficha.id', '=', 'users.servidor_id')
            ->leftJoin('unidades_administrativas as unidad', 'unidad.id', '=', 'ficha.unidad_administrativa_id');
    }

    private function conColumnas(Builder $query): Builder
    {
        return $query->select([
            'users.id as user_id', 'users.email', 'users.usuario_ti',
            'ficha.cedula', 'ficha.nombre', 'ficha.segundo_nombre', 'ficha.apellido', 'ficha.segundo_apellido',
            'unidad.nombre as unidad', 'lectura.leido_at', 'lectura.confirmado_at',
        ]);
    }

    /** Una dirección incluye a las unidades que cuelgan de ella. */
    private function deLaUnidad(Builder $query, int|string|null $unidadId): void
    {
        if (! empty($unidadId)) {
            $query->whereIn('ficha.unidad_administrativa_id', ArbolUnidades::conDescendientes((int) $unidadId));
        }
    }

    private function soloPendientes(Builder $query, Comunicado $comunicado): Builder
    {
        return $comunicado->requiere_confirmacion
            ? $query->whereNull('lectura.confirmado_at')
            : $query->whereNull('lectura.leido_at');
    }

    /** @return array<string, mixed> */
    private function fila(object $fila): array
    {
        $nombre = trim(implode(' ', array_filter([$fila->nombre, $fila->segundo_nombre, $fila->apellido, $fila->segundo_apellido])));

        return [
            'user_id'       => (int) $fila->user_id,
            'cedula'        => $fila->cedula,
            // Una cuenta sin ficha —un usuario técnico— se reconoce por su correo.
            'nombre'        => $nombre !== '' ? $nombre : ($fila->email ?? $fila->usuario_ti ?? "Usuario #{$fila->user_id}"),
            'unidad'        => $fila->unidad,
            'estado'        => $fila->confirmado_at ? 'confirmado' : ($fila->leido_at ? 'leido' : 'sin_leer'),
            'leido_at'      => $fila->leido_at ? Carbon::parse($fila->leido_at)->toIso8601String() : null,
            'confirmado_at' => $fila->confirmado_at ? Carbon::parse($fila->confirmado_at)->toIso8601String() : null,
        ];
    }
}
