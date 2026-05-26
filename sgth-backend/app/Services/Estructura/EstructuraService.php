<?php

namespace App\Services\Estructura;

use App\Contracts\Estructura\EstructuraServiceInterface;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Estructura\Puesto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\ReglaNegocioException;

final class EstructuraService implements EstructuraServiceInterface
{
    // ── GESTIÓN DE UNIDADES ADMINISTRATIVAS ──────────────────────────────────

    public function listarUnidades(array $filtros): LengthAwarePaginator
    {
        return UnidadAdministrativa::query()
            ->with(['padre'])
            ->when(isset($filtros['nivel']), fn($q) => $q->where('nivel', $filtros['nivel']))
            ->when(isset($filtros['estado']), fn($q) => $q->where('estado', $filtros['estado']))
            ->orderBy('nivel')
            ->orderBy('nombre')
            ->paginate($filtros['por_pagina'] ?? 15);
    }

    public function crearUnidad(array $datos): UnidadAdministrativa
    {
        return UnidadAdministrativa::create($datos);
    }

    public function obtenerUnidad(int $id): UnidadAdministrativa
    {
        return UnidadAdministrativa::with(['padre', 'hijos', 'puestos'])->findOrFail($id);
    }

    public function actualizarUnidad(int $id, array $datos): UnidadAdministrativa
    {
        $unidad = $this->obtenerUnidad($id);

        if (isset($datos['unidad_padre_id']) && $datos['unidad_padre_id'] == $id) {
            throw new ReglaNegocioException('Una unidad administrativa no puede ser hija de sí misma.');
        }

        $unidad->update($datos);
        return $unidad;
    }

    public function eliminarUnidad(int $id): void
    {
        $unidad = $this->obtenerUnidad($id);
        
        if ($unidad->hijos()->exists()) {
            throw new ReglaNegocioException('No se puede eliminar la unidad porque tiene subunidades o procesos dependientes.');
        }

        if ($unidad->puestos()->exists()) {
            throw new ReglaNegocioException('No se puede eliminar la unidad porque tiene puestos orgánicos asignados.');
        }

        $unidad->delete();
    }

    // ── ORGANIGRAMA ──────────────────────────────────────────────────────────

    public function obtenerOrganigrama(): Collection
    {
        return UnidadAdministrativa::whereNull('unidad_padre_id')
            ->where('estado', true)
            ->with(['tipoUnidad'])
            ->orderBy('nivel')
            ->orderBy('nombre')
            ->get()
            ->map(fn($u) => $this->cargarHijosRecursivo($u));
    }

    private function cargarHijosRecursivo(
        UnidadAdministrativa $unidad
    ): UnidadAdministrativa {
        $unidad->load([
            'tipoUnidad',
            'hijos' => fn($q) => $q->where('estado', true)
                                    ->orderBy('nombre'),
            'puestos' => fn($q) => $q->where('puestos.activo', true)
                                      ->leftJoin('cargos', 'puestos.cargo_id', '=', 'cargos.id')
                                      ->orderBy('cargos.nombre', 'asc')
                                      ->select('puestos.*'),
        ]);

        $unidad->hijos->each(
            fn($hijo) => $this->cargarHijosRecursivo($hijo)
        );

        return $unidad;
    }

    // ── GESTIÓN DE PUESTOS ───────────────────────────────────────────────────

    public function listarPuestos(array $filtros): LengthAwarePaginator
    {
        return Puesto::query()
            ->with(['cargo', 'unidadAdministrativa', 'grupoOcupacional'])
            ->join('cargos', 'cargos.id', '=', 'puestos.cargo_id')
            ->select('puestos.*')
            ->when(
                isset($filtros['unidad_administrativa_id']),
                fn($q) => $q->where(
                    'puestos.unidad_administrativa_id',
                    $filtros['unidad_administrativa_id']
                )
            )
            ->when(
                isset($filtros['regimen_laboral']),
                fn($q) => $q->where('puestos.regimen_laboral', $filtros['regimen_laboral'])
            )
            ->when(
                isset($filtros['es_jefe']),
                fn($q) => $q->where('puestos.es_jefe', $filtros['es_jefe'])
            )
            ->when(
                isset($filtros['activo']),
                fn($q) => $q->where('puestos.activo', $filtros['activo'])
            )
            ->orderBy('cargos.nombre', 'asc')
            ->paginate($filtros['per_page'] ?? 15);
    }

    public function crearPuesto(array $datos): Puesto
    {
        return DB::transaction(function () use ($datos) {
            // Regla de Negocio: Solo puede haber un Jefe por Unidad Administrativa
            if (isset($datos['es_jefe']) && $datos['es_jefe']) {
                $existeJefe = Puesto::where('unidad_administrativa_id', $datos['unidad_administrativa_id'])
                    ->where('es_jefe', true)
                    ->exists();

                if ($existeJefe) {
                    throw new ReglaNegocioException('La unidad administrativa seleccionada ya posee un puesto de conducción o jefatura activo.');
                }
            }

            return Puesto::create($datos)->load('cargo');
        });
    }

    public function obtenerPuesto(int $id): Puesto
    {
        return Puesto::with([
            'cargo',
            'unidadAdministrativa',
            'grupoOcupacional',
        ])->findOrFail($id);
    }

    public function actualizarPuesto(int $id, array $datos): Puesto
    {
        return DB::transaction(function () use ($id, $datos) {
            $puesto = $this->obtenerPuesto($id);

            if (isset($datos['es_jefe']) && $datos['es_jefe'] && !$puesto->es_jefe) {
                $unidadId = $datos['unidad_administrativa_id'] ?? $puesto->unidad_administrativa_id;
                
                $existeJefe = Puesto::where('unidad_administrativa_id', $unidadId)
                    ->where('es_jefe', true)
                    ->where('id', '!=', $puesto->id)
                    ->exists();

                if ($existeJefe) {
                    throw new ReglaNegocioException('La unidad administrativa ya posee un puesto de conducción asignado.');
                }
            }

            $puesto->update($datos);
            return $puesto;
        });
    }

    public function eliminarPuesto(int $id): void
    {
        $puesto = $this->obtenerPuesto($id);
        $puesto->delete();
    }
}
