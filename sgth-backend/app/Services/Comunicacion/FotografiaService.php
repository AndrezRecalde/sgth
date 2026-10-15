<?php

namespace App\Services\Comunicacion;

use App\Contracts\Comunicacion\FotografiaServiceInterface;
use App\Enums\OrigenFotografia;
use App\Exceptions\ReglaNegocioException;
use App\Models\Comunicacion\FotografiaServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Estructura\ArbolUnidades;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Las fotografías de los servidores (docs/diseno-comunicacion-social.md, 5.1).
 *
 * Reemplazar, restaurar y retirar solo cambian cuál es la vigente: ningún
 * archivo se borra, así que una foto equivocada siempre tiene vuelta atrás.
 * Cada cambio queda en el registro de actividad con quién lo hizo.
 */
final class FotografiaService implements FotografiaServiceInterface
{
    /** El disco privado: la foto es un dato personal y no se publica. */
    public const DISCO = 'local';

    private const POR_PAGINA = 24;
    private const MAXIMO_POR_PAGINA = 100;

    private const COLUMNAS_BUSQUEDA = [
        'cedula', 'nombre', 'segundo_nombre', 'apellido', 'segundo_apellido',
    ];

    public function __construct(private readonly ProcesadorFotografia $procesador) {}

    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = Servidor::query()->with([
            'fotografiaVigente.subidaPor.servidor',
            'unidadAdministrativa:id,nombre',
            'puesto.cargo',
        ]);

        // Por defecto, quienes trabajan hoy: la foto es para su credencial.
        match ($filtros['estado'] ?? 'activos') {
            'inactivos' => $query->where('estado', false),
            'todos'     => null,
            default     => $query->where('estado', true),
        };

        // Cada palabra en alguna columna, como el buscador del Expediente:
        // «Juan Pérez» encuentra a quien se llama Juan y se apellida Pérez.
        foreach (preg_split('/\s+/', trim((string) ($filtros['buscar'] ?? ''))) as $palabra) {
            if ($palabra === '') {
                continue;
            }
            $query->where(function ($q) use ($palabra) {
                foreach (self::COLUMNAS_BUSQUEDA as $columna) {
                    $q->orWhere($columna, 'ilike', "%{$palabra}%");
                }
            });
        }

        // Una dirección incluye a las unidades que cuelgan de ella.
        if (! empty($filtros['unidad_administrativa_id'])) {
            $query->whereIn(
                'unidad_administrativa_id',
                ArbolUnidades::conDescendientes((int) $filtros['unidad_administrativa_id']),
            );
        }

        match ($filtros['foto'] ?? null) {
            'con'   => $query->whereHas('fotografiaVigente'),
            'sin'   => $query->whereDoesntHave('fotografiaVigente'),
            default => null,
        };

        // Las fotos viejas, para renovarlas: cuenta cuándo se subió, no
        // cuándo se restauró.
        if (! empty($filtros['subida_antes_de'])) {
            $query->whereHas('fotografiaVigente', fn ($q) => $q->where('created_at', '<', $filtros['subida_antes_de']));
        }

        $porPagina = min(max((int) ($filtros['per_page'] ?? self::POR_PAGINA), 1), self::MAXIMO_POR_PAGINA);

        // El id desempata: sin él, dos homónimos se repiten o se pierden entre páginas.
        return $query->orderBy('apellido')->orderBy('nombre')->orderBy('id')->paginate($porPagina);
    }

    public function subir(Servidor $servidor, string $ruta, User $usuario, OrigenFotografia $origen): FotografiaServidor
    {
        // Se procesa antes de abrir la transacción: es lo lento, y si la
        // imagen no sirve no hay nada que deshacer.
        $imagenes = $this->procesador->procesar($ruta);

        $base = "fotografias/{$servidor->id}/".Str::uuid();
        $archivo = "{$base}.jpg";
        $miniatura = "{$base}-miniatura.jpg";
        $disco = Storage::disk(self::DISCO);
        $disco->put($archivo, $imagenes['completa']);
        $disco->put($miniatura, $imagenes['miniatura']);

        try {
            return DB::transaction(function () use ($servidor, $usuario, $origen, $archivo, $miniatura) {
                $this->bloquear($servidor);
                $this->dejarSinVigente($servidor);

                return $servidor->fotografias()->create([
                    'archivo'      => $archivo,
                    'miniatura'    => $miniatura,
                    'origen'       => $origen,
                    'vigente'      => true,
                    'activada_en'  => now(),
                    'activada_por' => $usuario->id,
                    'subida_por'   => $usuario->id,
                ]);
            });
        } catch (Throwable $e) {
            $disco->delete([$archivo, $miniatura]);

            throw $e;
        }
    }

    public function historial(Servidor $servidor): Collection
    {
        return $servidor->fotografias()
            ->with(['subidaPor.servidor', 'activadaPor.servidor'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function restaurar(FotografiaServidor $fotografia, User $usuario): FotografiaServidor
    {
        if ($fotografia->vigente) {
            throw new ReglaNegocioException('Esa foto ya es la vigente.');
        }

        return DB::transaction(function () use ($fotografia, $usuario) {
            $servidor = $fotografia->servidor;
            $this->bloquear($servidor);
            $this->dejarSinVigente($servidor);

            $fotografia->update([
                'vigente'      => true,
                'activada_en'  => now(),
                'activada_por' => $usuario->id,
            ]);

            return $fotografia;
        });
    }

    public function retirar(Servidor $servidor): FotografiaServidor
    {
        return DB::transaction(function () use ($servidor) {
            $this->bloquear($servidor);

            $vigente = $servidor->fotografiaVigente()->first()
                ?? throw new ReglaNegocioException('El servidor no tiene una foto vigente.');

            $vigente->update(['vigente' => false]);

            return $vigente;
        });
    }

    public function rutaImagen(FotografiaServidor $fotografia, bool $miniatura): string
    {
        return Storage::disk(self::DISCO)->path($miniatura ? $fotografia->miniatura : $fotografia->archivo);
    }

    /**
     * Toma la fila del servidor. Dos cambios a la vez sobre sus fotos esperan
     * su turno en vez de chocar con el índice de una sola vigente.
     */
    private function bloquear(Servidor $servidor): void
    {
        Servidor::withTrashed()->whereKey($servidor->id)->lockForUpdate()->first();
    }

    /** Uno por uno y no con un update masivo, para que quede en el registro. */
    private function dejarSinVigente(Servidor $servidor): void
    {
        $servidor->fotografias()->where('vigente', true)->get()
            ->each(fn (FotografiaServidor $f) => $f->update(['vigente' => false]));
    }
}
