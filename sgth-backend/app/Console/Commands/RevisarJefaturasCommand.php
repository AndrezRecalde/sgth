<?php

namespace App\Console\Commands;

use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Services\Estructura\FirmanteOrganigramaService;
use Illuminate\Console\Command;

/**
 * Lo que hay que corregir en los puestos para que cada jefe vea a su equipo.
 *
 * Desde el 2026-11-13 el jefe ve a su equipo por su puesto de jefatura, no por
 * el rol. Si a una unidad le falta la marca de jefatura, o el puesto no tiene
 * a nadie con contrato vigente, su jefe deja de ver los permisos y vacaciones
 * de su gente. Esto solo lee: no cambia nada.
 */
class RevisarJefaturasCommand extends Command
{
    protected $signature = 'sgth:jefaturas:revisar';

    protected $description = 'Lista las unidades sin jefatura clara: sin puesto de jefatura, con más de uno o vacantes.';

    public function handle(FirmanteOrganigramaService $firmantes): int
    {
        $this->sinJefatura();
        $this->conVariasJefaturas();
        $this->jefaturasSinQuienLasEjerza($firmantes);

        return self::SUCCESS;
    }

    private function sinJefatura(): void
    {
        $unidades = UnidadAdministrativa::query()
            ->where('estado', true)
            ->whereHas('puestos', fn ($q) => $q->where('activo', true))
            ->whereDoesntHave('puestos', fn ($q) => $q->where('activo', true)->where('es_jefe', true))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $this->seccion(
            'Unidades con puestos y sin puesto de jefatura',
            ['Id', 'Unidad'],
            $unidades->map(fn ($u) => [$u->id, $u->nombre])->all(),
        );
    }

    private function conVariasJefaturas(): void
    {
        $unidades = UnidadAdministrativa::query()
            ->where('estado', true)
            ->withCount(['puestos as jefaturas' => fn ($q) => $q->where('activo', true)->where('es_jefe', true)])
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->filter(fn ($u) => $u->jefaturas > 1);

        $this->seccion(
            'Unidades con más de un puesto de jefatura (firma el primero)',
            ['Id', 'Unidad', 'Jefaturas'],
            $unidades->map(fn ($u) => [$u->id, $u->nombre, $u->jefaturas])->values()->all(),
        );
    }

    private function jefaturasSinQuienLasEjerza(FirmanteOrganigramaService $firmantes): void
    {
        $hoy = now()->toDateString();

        $puestos = Puesto::query()
            ->where('activo', true)
            ->where('es_jefe', true)
            ->with(['unidadAdministrativa:id,nombre', 'cargo:id,nombre'])
            ->get()
            ->filter(fn (Puesto $p) => ! $firmantes->titularDe($p->id) && ! $firmantes->subroganteDe($p->id, $hoy))
            ->sortBy(fn (Puesto $p) => $p->unidadAdministrativa?->nombre);

        $this->seccion(
            'Jefaturas vacantes y sin subrogación hoy',
            ['Puesto', 'Cargo', 'Unidad'],
            $puestos->map(fn (Puesto $p) => [$p->id, $p->cargo?->nombre, $p->unidadAdministrativa?->nombre])->values()->all(),
        );
    }

    /** @param  list<array<int, mixed>>  $filas */
    private function seccion(string $titulo, array $cabecera, array $filas): void
    {
        $this->newLine();
        $this->line("<options=bold>{$titulo}</>");

        if ($filas === []) {
            $this->line('  Ninguna.');

            return;
        }

        $this->table($cabecera, $filas);
    }
}
