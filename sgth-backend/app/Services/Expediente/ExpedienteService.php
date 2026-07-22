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
        $query = $this->filtrarServidores(
            Servidor::query()->with(['unidadAdministrativa', 'puesto.cargo', 'contratoVigente']),
            $filtros
        );

        $perPage = isset($filtros['per_page'])
            ? (int) $filtros['per_page'] : 15;

        return $query->orderBy('apellido')->orderBy('nombre')
                     ->paginate($perPage);
    }

    /**
     * Filtros compartidos entre el listado paginado y la exportación de
     * nómina (Excel/PDF), para que ambos apliquen exactamente las mismas
     * reglas.
     */
    private function filtrarServidores($query, array $filtros)
    {
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

        // Estado propio del servidor (activo/inactivo), no confundir con el
        // estado del contrato (vigente/terminado/cancelado).
        if (isset($filtros['estado']) && $filtros['estado'] !== '') {
            $query->where('estado', filter_var($filtros['estado'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filtros['contrato_estado'])) {
            $query->whereHas('contratos', function ($q) use ($filtros) {
                $q->where('estado', $filtros['contrato_estado']);
            });
        }

        // Servidor "en funciones": activo y con contrato vigente.
        if (!empty($filtros['en_funciones'])) {
            $query->where('estado', true)->whereHas('contratoVigente');
        }

        if (!empty($filtros['tipo_nombramiento'])) {
            $query->where('tipo_nombramiento',
                $filtros['tipo_nombramiento']);
        }

        if (isset($filtros['tiene_discapacidad'])
            && $filtros['tiene_discapacidad']) {
            $query->conDiscapacidad();
        }

        // Año de ingreso a la institución.
        if (!empty($filtros['anio_ingreso'])) {
            $query->whereYear('fecha_ingreso_institucion', $filtros['anio_ingreso']);
        }

        return $query;
    }

    /**
     * Exporta el listado completo de servidores (sin paginar) con las
     * columnas del formato de nómina usado por Talento Humano.
     */
    public function exportarServidores(array $filtros): \Illuminate\Support\Collection
    {
        $query = $this->filtrarServidores(
            Servidor::query()->with([
                'unidadAdministrativa',
                'puesto.cargo',
                'puesto.grupoOcupacional',
                'contratoVigente',
                'usuario',
                'discapacidades',
                'historialAcademico',
            ]),
            $filtros
        );

        return $query->orderBy('apellido')->orderBy('nombre')->get()
            ->values()
            ->map(function (Servidor $servidor, int $index) {
                $puesto = $servidor->contratoVigente?->puesto ?? $servidor->puesto;
                $discapacidad = $servidor->discapacidades->first();
                $formacion = $servidor->historialAcademico
                    ->sortByDesc('fecha_fin')
                    ->first();

                $enFunciones = $servidor->estado && $servidor->contratoVigente;

                return [
                    'ITEM'                   => $index + 1,
                    'CÉDULA'                 => $servidor->cedula,
                    'NOMBRES Y APELLIDOS'    => trim(collect([
                        $servidor->apellido, $servidor->segundo_apellido,
                        $servidor->nombre, $servidor->segundo_nombre,
                    ])->filter()->join(' ')),
                    'GENERO'                 => $servidor->genero,
                    'ESTADO CIVIL'           => $servidor->estado_civil,
                    'TIPO DE DISCAPACIDAD'   => $discapacidad?->tipo_discapacidad?->etiqueta() ?? 'NO',
                    'PORCENTAJE'             => $discapacidad?->porcentaje ?? 0,
                    'CARGO'                  => $puesto?->cargo?->nombre,
                    'GRUPO OCUPACIONAL'      => $puesto?->grupoOcupacional?->denominacion_generica,
                    'R.M.U'                  => $servidor->contratoVigente?->remuneracion ?? $puesto?->rmu,
                    'R.A.U'                  => $servidor->contratoVigente?->rau,
                    'TIPO DE NOMBRAMIENTO'   => $servidor->tipo_nombramiento?->etiqueta(),
                    'GESTIÓN'                => $servidor->unidadAdministrativa?->nombre,
                    'FORMACIÓN'              => $formacion?->titulo_capacitacion,
                    'FECHA DE INGRESO'       => $servidor->fecha_ingreso_institucion?->format('Y-m-d'),
                    'FECHA DE SALIDA'        => $enFunciones ? 'EN FUNCIONES' : $servidor->contratoVigente?->fecha_fin?->format('Y-m-d'),
                    'FECHA DE NACIMIENTO'    => $servidor->fecha_nacimiento?->format('Y-m-d'),
                    'EDAD'                   => $servidor->fecha_nacimiento?->age,
                    'DIRECCIÓN'              => $servidor->direccion_domicilio,
                    'CELULAR'                => $servidor->telefono_celular,
                    'CORREO PERSONAL'        => $servidor->correo_personal,
                    'CORREO INSTITUCIONAL'   => $servidor->usuario?->email,
                ];
            });
    }
}
