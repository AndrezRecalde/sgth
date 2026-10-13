<?php

namespace App\Services\InventarioTi;

use App\Contracts\InventarioTi\BienInformaticoServiceInterface;
use App\Contracts\InventarioTi\MantenimientoServiceInterface;
use App\Enums\EstadoBien;
use App\Enums\Permiso;
use App\Exceptions\ReglaNegocioException;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Los mantenimientos de los equipos (docs/diseno-subsistema-tic.md, fase 1.5).
 *
 * El controlador era un muñón: `index` respondía `[]`, `store` decía
 * «Mantenimiento programado» sin guardar nada y `show` devolvía el eco del id.
 *
 * - Lo hace TIC (con su técnico) o el proveedor de la vigencia tecnológica,
 *   ligado al contrato que lo cubre.
 * - Al registrarlo se puede decir en qué estado queda el equipo: de vuelta en
 *   servicio, todavía en mantenimiento o con el proveedor. Pasa por la ficha
 *   del equipo, que es quien decide si es «en uso» o «disponible».
 * - Uno registrado por error se anula con su motivo; no se borra.
 */
final class MantenimientoService implements MantenimientoServiceInterface
{
    private const RELACIONES = [
        'bien:id,codigo_institucional,modelo,tipo_bien_id,marca_id', 'bien.tipo:id,nombre', 'bien.marca:id,nombre',
        'tecnico:id,usuario_ti,email,servidor_id', 'tecnico.servidor:id,nombre,apellido',
        'contratoVigencia:id,proceso,proveedor',
        'registradoPor:id,usuario_ti,email,servidor_id', 'registradoPor.servidor:id,nombre,apellido',
        'anuladoPor:id,usuario_ti,email,servidor_id', 'anuladoPor.servidor:id,nombre,apellido',
    ];

    public function __construct(private readonly BienInformaticoServiceInterface $bienes) {}

    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = MantenimientoBien::with(self::RELACIONES)
            ->when(empty($filtros['incluir_anulados']), fn (Builder $q) => $q->whereNull('anulado_en'))
            ->when($filtros['bien_informatico_id'] ?? null, fn (Builder $q, $id) => $q->where('bien_informatico_id', $id))
            ->when($filtros['tipo_mantenimiento'] ?? null, fn (Builder $q, $t) => $q->where('tipo_mantenimiento', $t))
            ->when($filtros['realizado_por'] ?? null, fn (Builder $q, $r) => $q->where('realizado_por', $r))
            ->when($filtros['tecnico_id'] ?? null, fn (Builder $q, $id) => $q->where('tecnico_id', $id))
            ->when($filtros['contrato_vigencia_id'] ?? null, fn (Builder $q, $id) => $q->where('contrato_vigencia_id', $id))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $d) => $q->where('fecha_mantenimiento', '>=', $d))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $h) => $q->where('fecha_mantenimiento', '<=', $h))
            ->when($filtros['search'] ?? null, fn (Builder $q, $s) => $q->where(fn (Builder $w) => $w
                ->whereHas('bien', fn (Builder $b) => $b->where('codigo_institucional', 'ilike', "%{$s}%"))
                ->orWhere('descripcion', 'ilike', "%{$s}%")
                ->orWhere('soporte_anterior', 'ilike', "%{$s}%")));

        // Los sin fecha (del Excel) al final: lo reciente es lo que se consulta.
        return $query->orderByRaw('fecha_mantenimiento DESC NULLS LAST')->orderByDesc('id')
            ->paginate(min(max((int) ($filtros['per_page'] ?? 20), 1), 100));
    }

    public function obtener(int $id): MantenimientoBien
    {
        return MantenimientoBien::with(self::RELACIONES)->findOrFail($id);
    }

    public function registrar(array $datos): MantenimientoBien
    {
        return DB::transaction(function () use ($datos) {
            $bien = BienInformatico::lockForUpdate()->findOrFail($datos['bien_informatico_id']);

            if ($bien->estado_operativo === EstadoBien::DADO_DE_BAJA) {
                throw new ReglaNegocioException('El equipo está dado de baja: ya no se le registran mantenimientos.');
            }

            $porProveedor = ($datos['realizado_por'] ?? MantenimientoBien::POR_TIC) === MantenimientoBien::POR_PROVEEDOR;

            $mantenimiento = MantenimientoBien::create([
                'bien_informatico_id'  => $bien->id,
                'ticket_id'            => $datos['ticket_id'] ?? null,
                'tipo_mantenimiento'   => $datos['tipo_mantenimiento'],
                'realizado_por'        => $porProveedor ? MantenimientoBien::POR_PROVEEDOR : MantenimientoBien::POR_TIC,
                // El del proveedor cuenta contra el contrato que cubre al equipo.
                'contrato_vigencia_id' => $porProveedor ? ($datos['contrato_vigencia_id'] ?? $bien->contrato_vigencia_id) : null,
                'fecha_mantenimiento'  => $datos['fecha_mantenimiento'],
                'tecnico_id'           => $porProveedor ? null : ($datos['tecnico_id'] ?? auth()->id()),
                'descripcion'          => $datos['descripcion'],
                'costo'                => $datos['costo'] ?? 0,
                'soporte_anterior'     => $datos['soporte_anterior'] ?? null,
                'created_by'           => auth()->id(),
            ]);

            if (! empty($datos['estado_equipo'])) {
                $this->bienes->actualizar($bien->id, ['estado_operativo' => $datos['estado_equipo']]);
            }

            return $this->obtener($mantenimiento->id);
        });
    }

    public function anular(MantenimientoBien $mantenimiento, string $motivo): MantenimientoBien
    {
        if ($mantenimiento->anulado_en !== null) {
            throw new ReglaNegocioException('Ese mantenimiento ya está anulado.');
        }

        $mantenimiento->update([
            'anulado_en'       => now(),
            'anulado_por'      => auth()->id(),
            'motivo_anulacion' => $motivo,
            'updated_by'       => auth()->id(),
        ]);

        return $this->obtener($mantenimiento->id);
    }

    public function tecnicos(): Collection
    {
        return User::permission(Permiso::REGISTRAR_MANTENIMIENTO->value)
            ->where('activo', true)
            ->with('servidor:id,nombre,apellido')
            ->orderBy('usuario_ti')
            ->get(['id', 'usuario_ti', 'email', 'servidor_id']);
    }
}
