<?php

namespace App\Services\Expediente;

use App\Contracts\Expediente\SubrogacionServiceInterface;
use App\Enums\EstadoSubrogacion;
use App\Enums\TipoSubrogacion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Subrogacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SubrogacionService implements SubrogacionServiceInterface
{
    public function registrar(array $datos): Subrogacion
    {
        // 1. Validación condicional de encargo vs subrogación
        if ($datos['tipo'] === TipoSubrogacion::ENCARGO->value) {
            $datos['servidor_subrogado_id'] = null;
        } elseif (empty($datos['servidor_subrogado_id'])) {
            throw new ReglaNegocioException("La subrogación requiere especificar el servidor titular a subrogar.");
        }

        // 2. Validación de fechas
        if (strtotime($datos['fecha_fin']) <= strtotime($datos['fecha_inicio'])) {
            throw new ReglaNegocioException("La fecha de fin debe ser estrictamente mayor a la fecha de inicio.");
        }

        // 3. Validar que no exista otra subrogación activa para este subrogante que se traslape
        $existeTraslape = Subrogacion::where('servidor_subrogante_id', $datos['servidor_subrogante_id'])
            ->where('estado', EstadoSubrogacion::ACTIVA)
            ->where(function ($query) use ($datos) {
                $query->whereBetween('fecha_inicio', [$datos['fecha_inicio'], $datos['fecha_fin']])
                      ->orWhereBetween('fecha_fin', [$datos['fecha_inicio'], $datos['fecha_fin']])
                      ->orWhere(function ($q) use ($datos) {
                          $q->where('fecha_inicio', '<=', $datos['fecha_inicio'])
                            ->where('fecha_fin', '>=', $datos['fecha_fin']);
                      });
            })->exists();

        if ($existeTraslape) {
            throw new ReglaNegocioException("El servidor ya cuenta con una subrogación/encargo activo en el rango de fechas indicado.");
        }

        // 4. Registro transaccional
        return DB::transaction(function () use ($datos) {
            $subrogacion = Subrogacion::create([
                'tipo'                     => $datos['tipo'],
                'servidor_subrogante_id'   => $datos['servidor_subrogante_id'],
                'servidor_subrogado_id'    => $datos['servidor_subrogado_id'] ?? null,
                'unidad_administrativa_id' => $datos['unidad_administrativa_id'],
                'puesto_subrogado_id'      => $datos['puesto_subrogado_id'],
                'fecha_inicio'             => $datos['fecha_inicio'],
                'fecha_fin'                => $datos['fecha_fin'],
                'motivo'                   => $datos['motivo'],
                'resolucion_numero'        => $datos['resolucion_numero'] ?? null,
                'documento_respaldo'       => $datos['documento_respaldo'] ?? null,
                'observacion'              => $datos['observacion'] ?? null,
                'estado'                   => EstadoSubrogacion::ACTIVA,
                'registrado_por'           => auth()->id(),
            ]);

            // Registrar en historial inmutable
            MovimientoPersonal::create([
                'servidor_id'       => $subrogacion->servidor_subrogante_id,
                'tipo_movimiento'   => 'subrogacion',
                'descripcion'       => "Inicio de {$subrogacion->tipo->etiqueta()} en la unidad seleccionada.",
                'fecha_efectiva'    => $subrogacion->fecha_inicio,
                'unidad_destino_id' => $subrogacion->unidad_administrativa_id,
                'puesto_destino_id' => $subrogacion->puesto_subrogado_id,
                'resolucion_numero' => $subrogacion->resolucion_numero,
                'autorizado_por'    => auth()->id(),
            ]);

            return $subrogacion;
        });
    }

    public function finalizar(int $subrogacionId): Subrogacion
    {
        return DB::transaction(function () use ($subrogacionId) {
            $subrogacion = Subrogacion::findOrFail($subrogacionId);
            
            if ($subrogacion->estado !== EstadoSubrogacion::ACTIVA) {
                throw new ReglaNegocioException("Solo se pueden finalizar subrogaciones activas.");
            }

            $subrogacion->update([
                'estado' => EstadoSubrogacion::FINALIZADA
            ]);

            // Registrar fin en historial
            MovimientoPersonal::create([
                'servidor_id'     => $subrogacion->servidor_subrogante_id,
                'tipo_movimiento' => 'subrogacion',
                'descripcion'     => "Finalización formal de {$subrogacion->tipo->etiqueta()}.",
                'fecha_efectiva'  => now()->toDateString(),
                'autorizado_por'  => auth()->id(),
            ]);

            return $subrogacion;
        });
    }

    public function cancelar(int $subrogacionId, string $motivo): Subrogacion
    {
        $subrogacion = Subrogacion::findOrFail($subrogacionId);
        
        if ($subrogacion->estado !== EstadoSubrogacion::ACTIVA) {
            throw new ReglaNegocioException("Solo se pueden cancelar subrogaciones activas.");
        }

        $subrogacion->update([
            'estado'      => EstadoSubrogacion::CANCELADA,
            'observacion' => trim($subrogacion->observacion . "\nCancelado: " . $motivo)
        ]);

        return $subrogacion;
    }

    public function listarActivas(): Collection
    {
        return Subrogacion::where('estado', EstadoSubrogacion::ACTIVA)
            ->where('fecha_inicio', '<=', now())
            ->where('fecha_fin', '>=', now())
            ->get();
    }

    public function listarPorServidor(int $servidorId): Collection
    {
        return Subrogacion::where('servidor_subrogante_id', $servidorId)
            ->orderBy('fecha_inicio', 'desc')
            ->get();
    }

    public function verificarSubrogacionActiva(int $servidorId, int $unidadId): ?Subrogacion
    {
        return Subrogacion::where('servidor_subrogante_id', $servidorId)
            ->where('unidad_administrativa_id', $unidadId)
            ->activaEnFecha(now())
            ->first();
    }
}
