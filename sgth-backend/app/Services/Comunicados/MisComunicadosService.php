<?php

namespace App\Services\Comunicados;

use App\Contracts\Comunicados\DestinatariosComunicadoServiceInterface;
use App\Contracts\Comunicados\MisComunicadosServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Comunicados\Comunicado;
use App\Models\Comunicados\ComunicadoAdjunto;
use App\Models\Comunicados\ComunicadoLectura;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Las noticias y comunicados en el Portal del servidor
 * (docs/diseno-noticias-comunicados.md, 3.4 y 5).
 *
 * A quién le corresponde cada una lo decide DestinatariosComunicadoService
 * (`visiblesPara`), la misma regla con que Talento Humano cuenta a quién llega.
 * Aquí se suma lo de cada persona: si la abrió y si confirmó haberla leído.
 */
final class MisComunicadosService implements MisComunicadosServiceInterface
{
    private const POR_PAGINA = 12;
    private const MAXIMO_POR_PAGINA = 50;

    /** Las del inicio y las de la campana. */
    private const EN_EL_INICIO = 5;

    public function __construct(private readonly DestinatariosComunicadoServiceInterface $destinatarios) {}

    public function listar(User $usuario, array $filtros): LengthAwarePaginator
    {
        $archivo = (bool) ($filtros['archivo'] ?? false);
        $query = $this->conMiLectura($archivo ? $this->vencidas($usuario) : $this->vigentes($usuario), $usuario)
            ->withCount('adjuntos');

        if (! empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        foreach (preg_split('/\s+/', trim((string) ($filtros['buscar'] ?? ''))) as $palabra) {
            if ($palabra === '') {
                continue;
            }
            $query->where(fn ($q) => $q->where('titulo', 'ilike', "%{$palabra}%")
                ->orWhere('referencia', 'ilike', "%{$palabra}%"));
        }

        match ($filtros['lectura'] ?? null) {
            'sin_leer'      => $this->sinLeer($query, $usuario),
            'por_confirmar' => $this->porConfirmar($query, $usuario),
            default         => null,
        };

        // Las fijadas primero entre las vigentes; en el archivo, la que venció
        // más tarde. El id desempata entre páginas.
        $archivo
            ? $query->orderByDesc('publicado_hasta')
            : $query->orderByDesc('fijado')->orderByDesc('publicado_desde');

        $porPagina = min(max((int) ($filtros['per_page'] ?? self::POR_PAGINA), 1), self::MAXIMO_POR_PAGINA);

        return $query->orderByDesc('id')->paginate($porPagina);
    }

    public function resumen(User $usuario): array
    {
        $recientes = $this->conMiLectura($this->vigentes($usuario), $usuario)
            ->orderByDesc('fijado')->orderByDesc('publicado_desde')->orderByDesc('id')
            ->limit(self::EN_EL_INICIO)
            ->get();

        $sinLeer = $this->sinLeer($this->vigentes($usuario), $usuario);

        return [
            'recientes'     => $recientes,
            // Las de la campana: las más nuevas que todavía no abrió.
            'no_leidos'     => $this->conMiLectura(clone $sinLeer, $usuario)
                ->orderByDesc('publicado_desde')->orderByDesc('id')
                ->limit(self::EN_EL_INICIO)
                ->get(),
            'sin_leer'      => $sinLeer->count(),
            'por_confirmar' => $this->porConfirmar($this->vigentes($usuario), $usuario)->count(),
        ];
    }

    public function abrir(User $usuario, int $comunicadoId): Comunicado
    {
        $comunicado = $this->visible($usuario, $comunicadoId);

        // La primera vez queda la fecha; las siguientes no la cambian. Con
        // `createOrFirst` dos pestañas abiertas a la vez no chocan en el índice único.
        ComunicadoLectura::createOrFirst(
            ['comunicado_id' => $comunicado->id, 'user_id' => $usuario->id],
            ['leido_at' => Carbon::now()],
        );

        return $this->conDetalle($comunicado, $usuario);
    }

    public function confirmar(User $usuario, int $comunicadoId): Comunicado
    {
        $comunicado = $this->visible($usuario, $comunicadoId);

        if (! $comunicado->requiere_confirmacion) {
            throw new ReglaNegocioException('Este comunicado no pide confirmación de lectura.');
        }

        if ($comunicado->publicado_hasta !== null && $comunicado->publicado_hasta->lt(Carbon::now())) {
            throw new ReglaNegocioException('Este comunicado ya venció: la confirmación de lectura se cerró.');
        }

        $ahora = Carbon::now();
        $lectura = ComunicadoLectura::createOrFirst(
            ['comunicado_id' => $comunicado->id, 'user_id' => $usuario->id],
            ['leido_at' => $ahora],
        );

        // Solo si no había confirmado: la fecha de la primera confirmación es
        // la que vale, y no se deshace.
        ComunicadoLectura::whereKey($lectura->id)->whereNull('confirmado_at')->update(['confirmado_at' => $ahora]);

        return $this->conDetalle($comunicado, $usuario);
    }

    public function adjunto(User $usuario, int $comunicadoId, int $adjuntoId): ComunicadoAdjunto
    {
        return $this->visible($usuario, $comunicadoId)->adjuntos()->whereKey($adjuntoId)->firstOrFail();
    }

    /** Una que le corresponde, vigente o vencida; si no, 404. */
    private function visible(User $usuario, int $comunicadoId): Comunicado
    {
        return $this->destinatarios->visiblesPara($usuario)->whereKey($comunicadoId)->firstOrFail();
    }

    /** @return Builder<Comunicado> */
    private function vigentes(User $usuario): Builder
    {
        return $this->destinatarios->visiblesPara($usuario)
            ->where(fn ($q) => $q->whereNull('publicado_hasta')->orWhere('publicado_hasta', '>=', Carbon::now()));
    }

    /** @return Builder<Comunicado> */
    private function vencidas(User $usuario): Builder
    {
        return $this->destinatarios->visiblesPara($usuario)->where('publicado_hasta', '<', Carbon::now());
    }

    /** Solo la lectura de esta persona, para saber si la abrió y si confirmó. */
    private function conMiLectura(Builder $query, User $usuario): Builder
    {
        return $query->with('lecturas', fn ($q) => $q->where('user_id', $usuario->id));
    }

    private function sinLeer(Builder $query, User $usuario): Builder
    {
        return $query->whereDoesntHave('lecturas', fn ($q) => $q->where('user_id', $usuario->id));
    }

    /** Las que piden confirmación y esta persona no ha confirmado. */
    private function porConfirmar(Builder $query, User $usuario): Builder
    {
        return $query->where('requiere_confirmacion', true)
            ->whereDoesntHave('lecturas', fn ($q) => $q->where('user_id', $usuario->id)->whereNotNull('confirmado_at'));
    }

    private function conDetalle(Comunicado $comunicado, User $usuario): Comunicado
    {
        return $comunicado->load([
            'adjuntos',
            'lecturas' => fn ($q) => $q->where('user_id', $usuario->id),
        ]);
    }
}
