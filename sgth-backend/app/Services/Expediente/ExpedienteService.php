<?php

namespace App\Services\Expediente;

use App\Contracts\Expediente\ExpedienteServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\DocumentoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExpedienteService implements ExpedienteServiceInterface
{
    public function crearServidor(array $datos): Servidor
    {
        return DB::transaction(function () use ($datos) {
            $servidor = Servidor::create($datos);

            // Registro inicial inmutable en Movimientos
            MovimientoPersonal::create([
                'servidor_id'       => $servidor->id,
                'tipo_movimiento'   => 'ingreso',
                'descripcion'       => 'Ingreso inicial del servidor al sistema SGTH.',
                'fecha_efectiva'    => $servidor->fecha_ingreso_institucion ?? now(),
                'unidad_destino_id' => $servidor->unidad_administrativa_id,
                'puesto_destino_id' => $servidor->puesto_id,
                'autorizado_por'    => auth()->id(),
            ]);

            return $servidor;
        });
    }

    public function crearServidorBasico(array $datos): Servidor
    {
        return DB::transaction(function () use ($datos) {
            $servidor = Servidor::create($datos);

            // Movimiento inicial de ingreso
            MovimientoPersonal::create([
                'servidor_id'     => $servidor->id,
                'tipo_movimiento' => 'ingreso',
                'descripcion'     => 'Registro inicial del servidor en el sistema SGTH.',
                'fecha_efectiva'  => now(),
                'autorizado_por'  => auth()->id(),
            ]);

            return $servidor;
        });
    }

    public function actualizarServidor(int $id, array $datos): Servidor
    {
        return DB::transaction(function () use ($id, $datos) {
            $servidor = Servidor::findOrFail($id);
            
            // Detectar cambios críticos (Ej. cambio de puesto o unidad)
            $cambioPuesto = isset($datos['puesto_id']) && $datos['puesto_id'] != $servidor->puesto_id;
            $cambioUnidad = isset($datos['unidad_administrativa_id']) && $datos['unidad_administrativa_id'] != $servidor->unidad_administrativa_id;

            if ($cambioPuesto || $cambioUnidad) {
                MovimientoPersonal::create([
                    'servidor_id'       => $servidor->id,
                    'tipo_movimiento'   => $cambioPuesto && $cambioUnidad ? 'traslado' : ($cambioPuesto ? 'cambio_puesto' : 'traslado'),
                    'descripcion'       => 'Actualización de expediente: Cambio de unidad o puesto.',
                    'fecha_efectiva'    => now(),
                    'unidad_origen_id'  => $servidor->unidad_administrativa_id,
                    'unidad_destino_id' => $datos['unidad_administrativa_id'] ?? $servidor->unidad_administrativa_id,
                    'puesto_origen_id'  => $servidor->puesto_id,
                    'puesto_destino_id' => $datos['puesto_id'] ?? $servidor->puesto_id,
                    'autorizado_por'    => auth()->id(),
                ]);
            }

            $servidor->update($datos);

            return $servidor;
        });
    }

    public function subirDocumento(int $servidorId, array $datos, UploadedFile $archivo): DocumentoServidor
    {
        $servidor = Servidor::findOrFail($servidorId);

        // Guardado seguro del archivo en storage
        $ruta = $archivo->storeAs(
            "expedientes/{$servidor->cedula}/documentos",
            time() . '_' . $archivo->getClientOriginalName(),
            'local' // Storage local blindado
        );

        if (!$ruta) {
            throw new ReglaNegocioException("Error al almacenar físicamente el archivo en el servidor.");
        }

        return DocumentoServidor::create([
            'servidor_id'       => $servidor->id,
            'tipo_documento'    => $datos['tipo_documento'],
            'nombre_archivo'    => $archivo->getClientOriginalName(),
            'ruta_archivo'      => $ruta,
            'tamanio_bytes'     => $archivo->getSize(),
            'mime_type'         => $archivo->getMimeType(),
            'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
            'descripcion'       => $datos['descripcion'] ?? null,
            'estado'            => true,
            'subido_por'        => auth()->id(),
        ]);
    }

    public function obtenerExpedienteCompleto(int $servidorId): Servidor
    {
        return Servidor::with([
            'user', 
            'unidadAdministrativa', 
            'puesto', 
            'documentos', 
            'movimientos' => function($q) {
                $q->orderBy('fecha_efectiva', 'desc');
            }
        ])->findOrFail($servidorId);
    }

    public function listarServidores(array $filtros): mixed
    {
        $query = Servidor::query()
            ->with(['unidadAdministrativa', 'puesto.cargo']);

        // Búsqueda por nombre o cédula
        if (!empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('cedula', 'ilike', "%{$search}%")
                  ->orWhere('nombre', 'ilike', "%{$search}%")
                  ->orWhere('apellido', 'ilike', "%{$search}%")
                  ->orWhere('segundo_nombre', 'ilike', "%{$search}%")
                  ->orWhere('segundo_apellido', 'ilike', "%{$search}%");
            });
        }

        if (!empty($filtros['unidad_administrativa_id'])) {
            $query->where('unidad_administrativa_id',
                $filtros['unidad_administrativa_id']);
        }

        if (isset($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (!empty($filtros['tipo_nombramiento'])) {
            $query->where('tipo_nombramiento',
                $filtros['tipo_nombramiento']);
        }

        if (isset($filtros['tiene_discapacidad'])
            && $filtros['tiene_discapacidad']) {
            $query->conDiscapacidad();
        }

        $perPage = isset($filtros['per_page'])
            ? (int) $filtros['per_page'] : 15;

        return $query->orderBy('apellido')->orderBy('nombre')
                     ->paginate($perPage);
    }
}
