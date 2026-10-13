<?php

namespace App\Services\Actividades;

use App\Exceptions\ReglaNegocioException;
use App\Models\Actividades\ActividadLaboral;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Estructura\FirmanteOrganigramaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Las actividades laborales que el servidor anota en su portal (decisiones del
 * 2026-10-12): son suyas y nadie más las ve ni las aprueba; se anotan por día,
 * sin horas, y se corrigen solo dentro del mes en curso —las de meses
 * anteriores quedan fijas—.
 */
class MisActividadesService
{
    public const POR_PAGINA = 15;

    public function __construct(private readonly FirmanteOrganigramaService $organigrama) {}

    /** Las suyas, de la más reciente a la más antigua. */
    public function listar(User $usuario, array $filtros): LengthAwarePaginator
    {
        return $this->consulta($usuario, $filtros)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(min((int) ($filtros['per_page'] ?? self::POR_PAGINA), 100));
    }

    /** Cuántas pasan el filtro, no solo las de la página. */
    public function total(User $usuario, array $filtros): int
    {
        return $this->consulta($usuario, $filtros)->count();
    }

    public function registrar(User $usuario, array $datos): ActividadLaboral
    {
        $servidorId = $usuario->servidor_id
            ?? throw new ReglaNegocioException('Su cuenta no está vinculada a un servidor: no puede anotar actividades.');

        $this->exigirMesEnCurso($datos['fecha']);

        return ActividadLaboral::create([...$this->campos($datos), 'servidor_id' => $servidorId]);
    }

    public function actualizar(User $usuario, int $id, array $datos): ActividadLaboral
    {
        $actividad = $this->editableDeLaCuenta($usuario, $id);
        $this->exigirMesEnCurso($datos['fecha']);
        $actividad->update($this->campos($datos));

        return $actividad->fresh();
    }

    public function eliminar(User $usuario, int $id): void
    {
        $this->editableDeLaCuenta($usuario, $id)->delete();
    }

    /** Una de las suyas, o 404. */
    public function deLaCuenta(User $usuario, int $id): ActividadLaboral
    {
        return ActividadLaboral::where('servidor_id', $usuario->servidor_id ?? 0)->findOrFail($id);
    }

    /** Las del mes en curso; las de antes quedan fijas. */
    public static function esEditable(ActividadLaboral $actividad): bool
    {
        return $actividad->fecha->isSameMonth(now());
    }

    /**
     * Lo que lleva el informe: quién lo presenta, a quién, sus actividades en
     * orden cronológico y el período en letras.
     *
     * @return array{servidor: Servidor, cargo: ?string, unidad: ?string, actividades: Collection<int, ActividadLaboral>, jefe: array{nombre: ?string, cargo: string}, periodo: string}
     */
    public function paraInforme(User $usuario, array $filtros): array
    {
        $servidor = Servidor::with(['contratoVigente', 'puesto.cargo', 'unidadAdministrativa'])
            ->find($usuario->servidor_id ?? 0)
            ?? throw new ReglaNegocioException('Su cuenta no está vinculada a un servidor: no hay actividades que exportar.');

        $contrato = $servidor->contratoVigente;

        return [
            'servidor'    => $servidor,
            'cargo'       => $contrato?->puesto?->cargo?->nombre ?? $servidor->puesto?->cargo?->nombre,
            'unidad'      => $contrato?->unidadAdministrativa?->nombre ?? $servidor->unidadAdministrativa?->nombre,
            'actividades' => $this->consulta($usuario, $filtros)->orderBy('fecha')->orderBy('id')->get(),
            'jefe'        => $this->jefeInmediato($servidor, $contrato?->unidad_administrativa_id ?? $servidor->unidad_administrativa_id),
            'periodo'     => self::periodo(Carbon::parse($filtros['desde']), Carbon::parse($filtros['hasta'])),
        ];
    }

    /**
     * El período en letras, con las dos fechas del filtro completas (pedido de
     * TH, 2026-10-12): «DEL 01 DE OCTUBRE DE 2026 AL 31 DE OCTUBRE DE 2026».
     * Un solo día: «EL 12 DE OCTUBRE DE 2026».
     */
    public static function periodo(Carbon $desde, Carbon $hasta): string
    {
        $enLetras = fn (Carbon $f) => mb_strtoupper($f->locale('es')->translatedFormat('d \d\e F \d\e Y'), 'UTF-8');

        return $desde->isSameDay($hasta)
            ? "EL {$enLetras($hasta)}"
            : "DEL {$enLetras($desde)} AL {$enLetras($hasta)}";
    }

    // ── Internos ─────────────────────────────────────────────────

    private function consulta(User $usuario, array $filtros): Builder
    {
        return ActividadLaboral::query()
            ->where('servidor_id', $usuario->servidor_id ?? 0)
            ->when($filtros['desde'] ?? null, fn ($q, $d) => $q->whereDate('fecha', '>=', $d))
            ->when($filtros['hasta'] ?? null, fn ($q, $h) => $q->whereDate('fecha', '<=', $h))
            ->when($filtros['categoria'] ?? null, fn ($q, $c) => $q->where('categoria', $c))
            ->when(filled($filtros['buscar'] ?? null), function ($q) use ($filtros) {
                $texto = '%'.addcslashes(trim($filtros['buscar']), '%_\\').'%';
                $q->whereAny(['descripcion', 'producto_entregable'], 'ilike', $texto);
            });
    }

    private function editableDeLaCuenta(User $usuario, int $id): ActividadLaboral
    {
        $actividad = $this->deLaCuenta($usuario, $id);

        if (! self::esEditable($actividad)) {
            throw new ReglaNegocioException(
                'Es de '.$this->mes($actividad->fecha).': las actividades de meses anteriores ya no se corrigen ni se eliminan.'
            );
        }

        return $actividad;
    }

    /** Se anotan las del mes en curso: el mes cerrado queda como se informó. */
    private function exigirMesEnCurso(string $fecha): void
    {
        if (! Carbon::parse($fecha)->isSameMonth(now())) {
            throw ValidationException::withMessages([
                'fecha' => 'Solo se anotan actividades de '.$this->mes(now()).'.',
            ]);
        }
    }

    private function campos(array $datos): array
    {
        return [
            'fecha'               => $datos['fecha'],
            'descripcion'         => trim($datos['descripcion']),
            'categoria'           => $datos['categoria'],
            'producto_entregable' => filled($datos['producto_entregable'] ?? null) ? trim($datos['producto_entregable']) : null,
        ];
    }

    /**
     * A quien va dirigido el informe, según el organigrama (decisión del
     * 2026-10-12): el titular del puesto de jefatura de su unidad, o quien lo
     * ejerza hoy por encargo o subrogación. Si el servidor es el jefe, el de la
     * unidad de arriba. Sin titular ni encargo registrado, el cargo sin nombre:
     * mejor eso que atribuirle a alguien un informe que no recibió.
     *
     * @return array{nombre: ?string, cargo: string}
     */
    private function jefeInmediato(Servidor $servidor, ?int $unidadId): array
    {
        $hoy = now()->toDateString();
        $firma = $this->organigrama->jefeDeUnidadEn($unidadId, $hoy, 'Jefe inmediato');

        if ($firma['servidor']?->id === $servidor->id) {
            $padre = UnidadAdministrativa::whereKey($unidadId)->value('unidad_padre_id');
            $firma = $padre
                ? $this->organigrama->jefeDeUnidadEn($padre, $hoy, 'Jefe inmediato')
                : ['servidor' => null, 'cargo' => 'Jefe inmediato', 'subrogado' => false, 'encargo' => false];
        }

        return [
            'nombre' => $firma['servidor'] ? $this->organigrama->nombreCompleto($firma['servidor']) : null,
            // Quien lo ejerce sin ser titular: (E) por encargo, (S) por subrogación.
            'cargo'  => ($firma['cargo'] ?? 'Jefe inmediato').match (true) {
                $firma['encargo']   => ' (E)',
                $firma['subrogado'] => ' (S)',
                default             => '',
            },
        ];
    }

    private function mes(Carbon $fecha): string
    {
        return $fecha->locale('es')->translatedFormat('F \d\e Y');
    }
}
