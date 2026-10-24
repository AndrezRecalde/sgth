<?php

namespace App\Services\Comunicados;

use App\Contracts\Comunicados\ArchivosComunicadoServiceInterface;
use App\Contracts\Comunicados\ComunicadoServiceInterface;
use App\Contracts\Comunicados\DestinatariosComunicadoServiceInterface;
use App\Enums\AudienciaComunicado;
use App\Enums\EstadoComunicado;
use App\Enums\TipoComunicado;
use App\Enums\VigenciaComunicado;
use App\Exceptions\ReglaNegocioException;
use App\Models\Comunicados\Comunicado;
use App\Models\User;
use App\Support\HtmlEnriquecido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El ciclo de una noticia o un comunicado (docs/diseno-noticias-comunicados.md,
 * 3.2 y 4).
 *
 * Los permisos los pide la ruta; aquí viven las reglas de estado, que valen
 * también para admin-ti, a quien el Gate::before deja pasar cualquier permiso.
 */
final class ComunicadoService implements ComunicadoServiceInterface
{
    /** Hasta tres publicaciones fijadas a la vez (3.5). */
    public const MAXIMO_FIJADOS = 3;

    private const POR_PAGINA = 20;
    private const MAXIMO_POR_PAGINA = 100;

    private const RELACIONES_LISTADO = [
        'creadoPor.servidor', 'publicadoPor.servidor', 'unidadesSeleccionadas:id,nombre',
    ];

    public function __construct(
        private readonly DestinatariosComunicadoServiceInterface $destinatarios,
        private readonly ArchivosComunicadoServiceInterface $archivos,
    ) {}

    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = Comunicado::query()
            ->with(self::RELACIONES_LISTADO)
            ->withCount([
                'adjuntos',
                'lecturas as leidos_count',
                'lecturas as confirmados_count' => fn ($q) => $q->whereNotNull('confirmado_at'),
            ]);

        if (! empty($filtros['vigencia'])) {
            $query->enVigencia(VigenciaComunicado::from($filtros['vigencia']));
        }

        if (! empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (! empty($filtros['anio'])) {
            $query->whereYear('created_at', (int) $filtros['anio']);
        }

        // Cada palabra en el título o en el número del documento.
        foreach (preg_split('/\s+/', trim((string) ($filtros['buscar'] ?? ''))) as $palabra) {
            if ($palabra === '') {
                continue;
            }
            $query->where(fn ($q) => $q->where('titulo', 'ilike', "%{$palabra}%")
                ->orWhere('referencia', 'ilike', "%{$palabra}%"));
        }

        $porPagina = min(max((int) ($filtros['per_page'] ?? self::POR_PAGINA), 1), self::MAXIMO_POR_PAGINA);

        // El id desempata: dos creados en el mismo segundo no se repiten entre páginas.
        $pagina = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($porPagina);

        foreach ($pagina->getCollection() as $comunicado) {
            $comunicado->totalDestinatarios = $this->destinatarios->contar($comunicado);
        }

        return $pagina;
    }

    public function crear(array $datos, User $usuario): Comunicado
    {
        return DB::transaction(function () use ($datos, $usuario) {
            $comunicado = Comunicado::create([
                ...$this->normalizar($datos),
                'estado'     => EstadoComunicado::BORRADOR,
                'creado_por' => $usuario->id,
            ]);

            $this->sincronizarUnidades($comunicado, $datos);

            return $comunicado;
        });
    }

    public function actualizar(Comunicado $comunicado, array $datos): Comunicado
    {
        return DB::transaction(function () use ($comunicado, $datos) {
            $bloqueado = $this->bloquear($comunicado);
            $this->exigirBorrador($bloqueado, 'Solo se edita un borrador. Para corregir lo publicado, retírelo y duplíquelo.');

            $bloqueado->update($this->normalizar($datos));
            $this->sincronizarUnidades($bloqueado, $datos);

            return $bloqueado;
        });
    }

    public function eliminar(Comunicado $comunicado): void
    {
        DB::transaction(function () use ($comunicado) {
            $bloqueado = $this->bloquear($comunicado);
            $this->exigirBorrador($bloqueado, 'Solo se elimina un borrador. Lo publicado se retira y queda en el historial.');

            $bloqueado->delete();
        });

        $this->archivos->eliminarTodo($comunicado->id);
    }

    public function publicar(Comunicado $comunicado, User $usuario): Comunicado
    {
        return DB::transaction(function () use ($comunicado, $usuario) {
            $bloqueado = $this->bloquear($comunicado);
            $this->exigirBorrador($bloqueado, 'Solo se publica un borrador.');
            $this->exigirCompleto($bloqueado);

            $ahora = Carbon::now();
            $desde = $bloqueado->publicado_desde;

            if ($desde !== null && $desde->lt($ahora->copy()->startOfDay())) {
                throw new ReglaNegocioException('La fecha de inicio ya pasó: cámbiela antes de publicar.');
            }

            // Hoy, o sin fecha, sale ya; una fecha futura lo deja programado.
            $desde = ($desde === null || $desde->isSameDay($ahora)) ? $ahora : $desde->copy()->startOfDay();
            $hasta = $bloqueado->publicado_hasta;

            if ($hasta->lt($ahora)) {
                throw new ReglaNegocioException('La fecha de fin ya pasó: cámbiela antes de publicar.');
            }

            if ($hasta->lt($desde)) {
                throw new ReglaNegocioException('La fecha de fin es anterior a la de inicio.');
            }

            if ($bloqueado->fijado) {
                $this->exigirLugarParaFijar($bloqueado);
            }

            $unidadIds = $this->congelarDependencias($bloqueado);
            $alcance = $this->destinatarios
                ->usuarios($bloqueado->audiencia, $unidadIds, $bloqueado->regimenes ?? [])
                ->count();

            if ($alcance === 0) {
                throw new ReglaNegocioException(
                    'Nadie recibiría esta publicación: ningún servidor activo cumple los destinatarios elegidos.'
                );
            }

            $bloqueado->update([
                'estado'          => EstadoComunicado::PUBLICADO,
                'publicado_desde' => $desde,
                'publicado_por'   => $usuario->id,
                'publicado_at'    => $ahora,
            ]);

            return $bloqueado;
        });
    }

    public function retirar(Comunicado $comunicado, string $motivo, User $usuario): Comunicado
    {
        return DB::transaction(function () use ($comunicado, $motivo, $usuario) {
            $bloqueado = $this->bloquear($comunicado);

            if ($bloqueado->estado !== EstadoComunicado::PUBLICADO) {
                throw new ReglaNegocioException('Solo se retira lo publicado. Un borrador se elimina.');
            }

            $bloqueado->update([
                'estado'        => EstadoComunicado::RETIRADO,
                'retirado_por'  => $usuario->id,
                'retirado_at'   => Carbon::now(),
                'motivo_retiro' => trim($motivo),
            ]);

            return $bloqueado;
        });
    }

    public function duplicar(Comunicado $comunicado, User $usuario): Comunicado
    {
        $copia = DB::transaction(function () use ($comunicado, $usuario) {
            $copia = Comunicado::create([
                'tipo'                  => $comunicado->tipo,
                'titulo'                => $comunicado->titulo,
                'resumen'               => $comunicado->resumen,
                'cuerpo'                => $comunicado->cuerpo,
                'referencia'            => $comunicado->referencia,
                'audiencia'             => $comunicado->audiencia,
                'regimenes'             => $comunicado->regimenes,
                'requiere_confirmacion' => $comunicado->requiere_confirmacion,
                // Las fechas y el fijado se vuelven a decidir: las del
                // original casi siempre ya no sirven.
                'fijado'                => false,
                'estado'                => EstadoComunicado::BORRADOR,
                'duplicado_de_id'       => $comunicado->id,
                'creado_por'            => $usuario->id,
            ]);

            // Solo lo que eligió Talento Humano: las dependencias se vuelven a
            // calcular al publicar la copia, con el orgánico de ese día.
            $copia->unidades()->sync(
                $comunicado->unidadesSeleccionadas()->get()->mapWithKeys(fn ($unidad) => [
                    $unidad->id => [
                        'seleccionada'         => true,
                        'incluye_dependencias' => (bool) $unidad->pivot->incluye_dependencias,
                    ],
                ])->all()
            );

            return $copia;
        });

        try {
            $this->archivos->copiar($comunicado, $copia);
        } catch (\Throwable $e) {
            // Sin sus archivos la copia confundiría: se deshace entera.
            $copia->delete();
            $this->archivos->eliminarTodo($copia->id);

            throw $e;
        }

        return $copia->refresh();
    }

    public function actualizarPublicacion(Comunicado $comunicado, array $datos): Comunicado
    {
        return DB::transaction(function () use ($comunicado, $datos) {
            $bloqueado = $this->bloquear($comunicado);

            if ($bloqueado->estado !== EstadoComunicado::PUBLICADO) {
                throw new ReglaNegocioException(
                    'Solo se cambia la publicación de lo publicado. Un borrador se edita completo; lo retirado ya no cambia.'
                );
            }

            $cambios = [];

            if (array_key_exists('publicado_hasta', $datos)) {
                // Se alarga o se acorta, pero no se quita (2026-10-24): sin
                // fecha de fin, lo publicado se quedaba en el Portal para siempre.
                if ($datos['publicado_hasta'] === null) {
                    throw new ReglaNegocioException('Lo publicado necesita fecha de fin: elija otra en vez de quitarla.');
                }

                $hasta = Carbon::parse($datos['publicado_hasta'])->endOfDay();

                if ($bloqueado->publicado_desde !== null && $hasta->lt($bloqueado->publicado_desde)) {
                    throw new ReglaNegocioException('La fecha de fin es anterior a la de inicio.');
                }

                $cambios['publicado_hasta'] = $hasta;
            }

            if (array_key_exists('fijado', $datos)) {
                $fijado = (bool) $datos['fijado'];

                if ($fijado && ! $bloqueado->fijado) {
                    $this->exigirLugarParaFijar($bloqueado);
                }

                $cambios['fijado'] = $fijado;
            }

            $bloqueado->update($cambios);

            return $bloqueado;
        });
    }

    /**
     * Los campos del formulario, ya limpios. La referencia y la confirmación
     * son del comunicado oficial: en una noticia se descartan sin error, para
     * que cambiar el tipo en el formulario no obligue a vaciarlas a mano.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos): array
    {
        $tipo = TipoComunicado::from($datos['tipo']);
        $audiencia = AudienciaComunicado::from($datos['audiencia']);
        $referencia = trim((string) ($datos['referencia'] ?? ''));
        $regimenes = array_values(array_unique($datos['regimenes'] ?? []));

        return [
            'tipo'                  => $tipo,
            'titulo'                => trim($datos['titulo']),
            'resumen'               => ($resumen = trim((string) ($datos['resumen'] ?? ''))) === '' ? null : $resumen,
            'cuerpo'                => HtmlEnriquecido::limpiar($datos['cuerpo'] ?? null) ?: null,
            'referencia'            => $tipo->esOficial() && $referencia !== '' ? $referencia : null,
            'audiencia'             => $audiencia,
            'regimenes'             => $audiencia === AudienciaComunicado::SEGMENTADA && $regimenes !== [] ? $regimenes : null,
            'publicado_desde'       => empty($datos['publicado_desde']) ? null : Carbon::parse($datos['publicado_desde'])->startOfDay(),
            'publicado_hasta'       => empty($datos['publicado_hasta']) ? null : Carbon::parse($datos['publicado_hasta'])->endOfDay(),
            'fijado'                => (bool) ($datos['fijado'] ?? false),
            'requiere_confirmacion' => $tipo->esOficial() && (bool) ($datos['requiere_confirmacion'] ?? false),
        ];
    }

    /** @param  array<string, mixed>  $datos */
    private function sincronizarUnidades(Comunicado $comunicado, array $datos): void
    {
        $unidades = $comunicado->audiencia === AudienciaComunicado::SEGMENTADA ? ($datos['unidades'] ?? []) : [];

        $comunicado->unidades()->sync(
            collect($unidades)->mapWithKeys(fn ($unidad) => [
                (int) $unidad['id'] => [
                    'seleccionada'         => true,
                    'incluye_dependencias' => (bool) ($unidad['incluye_dependencias'] ?? false),
                ],
            ])->all()
        );
    }

    /**
     * Agrega como no elegidas las dependencias de cada unidad que las pidió.
     * Desde aquí, a quién va ya no cambia con el orgánico.
     *
     * @return list<int> todas las unidades a las que va
     */
    private function congelarDependencias(Comunicado $comunicado): array
    {
        $seleccion = $comunicado->unidadesSeleccionadas()->get();
        $elegidas = $seleccion->pluck('id')->map(fn ($id) => (int) $id)->all();

        $todas = $this->destinatarios->expandir($seleccion->map(fn ($unidad) => [
            'id'                   => (int) $unidad->id,
            'incluye_dependencias' => (bool) $unidad->pivot->incluye_dependencias,
        ])->all());

        $nuevas = array_values(array_diff($todas, $elegidas));

        if ($nuevas !== []) {
            $comunicado->unidades()->attach(
                array_fill_keys($nuevas, ['seleccionada' => false, 'incluye_dependencias' => false])
            );
        }

        return $todas;
    }

    private function bloquear(Comunicado $comunicado): Comunicado
    {
        return Comunicado::whereKey($comunicado->id)->lockForUpdate()->firstOrFail();
    }

    private function exigirBorrador(Comunicado $comunicado, string $mensaje): void
    {
        if (! $comunicado->esBorrador()) {
            throw new ReglaNegocioException($mensaje);
        }
    }

    /**
     * Lo que se exige para publicar (regla 4). El resumen no: sin él, la
     * tarjeta del Portal muestra el comienzo del texto.
     */
    private function exigirCompleto(Comunicado $comunicado): void
    {
        if (! HtmlEnriquecido::tieneTexto($comunicado->cuerpo)) {
            throw new ReglaNegocioException('Para publicar falta el texto.');
        }

        // Obligatoria desde el 2026-10-24: sin ella, lo publicado no salía nunca
        // del Portal. El borrador sí se guarda sin fecha de fin.
        if ($comunicado->publicado_hasta === null) {
            throw new ReglaNegocioException('Para publicar falta la fecha de fin: elija hasta cuándo se verá en el Portal.');
        }

        if ($comunicado->audiencia === AudienciaComunicado::SEGMENTADA
            && $comunicado->regimenes === null
            && ! $comunicado->unidadesSeleccionadas()->exists()) {
            throw new ReglaNegocioException('Elija al menos una dirección o un régimen, o envíelo a todos.');
        }
    }

    /**
     * Tres fijados a la vez como máximo. El candado evita que dos publicaciones
     * simultáneas ocupen el último lugar las dos.
     */
    private function exigirLugarParaFijar(Comunicado $comunicado): void
    {
        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ['comunicados_fijados']);

        $ocupados = Comunicado::query()
            ->fijadosQueCuentan()
            ->whereKeyNot($comunicado->id)
            ->count();

        if ($ocupados >= self::MAXIMO_FIJADOS) {
            throw new ReglaNegocioException(
                'Ya hay '.self::MAXIMO_FIJADOS.' publicaciones fijadas: desfije una antes de fijar otra.'
            );
        }
    }
}
