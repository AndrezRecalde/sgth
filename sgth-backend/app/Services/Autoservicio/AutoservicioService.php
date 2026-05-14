<?php

namespace App\Services\Autoservicio;

use App\Contracts\Autoservicio\AutoservicioServiceInterface;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Asistencia\Vacacion;
use App\Models\Nomina\RolPago;
use App\Models\Expediente\Servidor;
// Nota: Modelos Actividad, CitaMedica e HistoriaClinica simulados o básicos para este Sprint
use App\Contracts\Asistencia\VacacionServiceInterface;
use Illuminate\Support\Facades\DB;
use App\Enums\TipoPermiso;

final class AutoservicioService implements AutoservicioServiceInterface
{
    public function __construct(private VacacionServiceInterface $vacacionService) {}

    public function obtenerMisPermisos(int $servidorId, array $filtros): array
    {
        $query = PermisoServidor::where('servidor_id', $servidorId);

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }
        if (!empty($filtros['anio'])) {
            $query->whereYear('fecha', $filtros['anio']);
        }

        $permisos = $query->orderBy('fecha', 'desc')->get()->map(function ($permiso) {
            $data = $permiso->toArray();
            // REGLA: NO muestra el motivo (observacion) si tipo = personal
            if ($permiso->tipo === TipoPermiso::PERSONAL->value) {
                $data['observacion'] = 'Confidencial (Permiso Personal)';
            }
            return $data;
        });

        return $permisos->toArray();
    }

    public function obtenerMisVacaciones(int $servidorId): array
    {
        $saldo = $this->vacacionService->calcularSaldoActual($servidorId);
        
        $solicitudes = Vacacion::where('servidor_id', $servidorId)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'saldo_dias_disponibles' => $saldo,
            'historial_solicitudes'  => $solicitudes
        ];
    }

    public function obtenerMisRolesPago(int $servidorId): array
    {
        // Traemos los roles sin exponer los detalles internos a menos que sea requerido
        return RolPago::with('nomina:id,periodo,estado')
            ->where('servidor_id', $servidorId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function obtenerMiExpediente(int $servidorId): array
    {
        $servidor = Servidor::with(['documentos' => function ($query) {
            $query->select('id', 'servidor_id', 'tipo_documento', 'nombre_archivo', 'created_at');
            // Nota: Se omitió 'ruta_archivo' explícitamente en el select para no exponer rutas físicas
        }])->findOrFail($servidorId);

        return $servidor->toArray();
    }

    public function obtenerMisActividades(int $servidorId): array
    {
        // En el Sprint 11 se implementarán las tablas reales. Aquí preparamos la firma.
        return DB::table('actividades_laborales')
            ->where('servidor_id', $servidorId)
            ->orderBy('fecha', 'desc')
            ->get()
            ->toArray();
    }

    public function solicitarCitaMedica(int $servidorId, array $datos): array
    {
        // En un futuro (Sprint 9) interactúa con DispensarioMédico.
        // Simulamos la creación e integración con Módulo 04.
        
        DB::beginTransaction();
        try {
            $citaId = DB::table('agendas_medicas')->insertGetId([
                'servidor_id' => $servidorId,
                'fecha_hora'  => $datos['fecha_hora'],
                'sintomas'    => $datos['sintomas'],
                'estado'      => 'programada',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // Genera permiso por enfermedad pendiente automáticamente si es en jornada laboral (asumimos que sí)
            $permiso = PermisoServidor::create([
                'servidor_id' => $servidorId,
                'tipo'        => TipoPermiso::ENFERMEDAD->value,
                'fecha'       => date('Y-m-d', strtotime($datos['fecha_hora'])),
                'hora_inicio' => date('H:i:s', strtotime($datos['fecha_hora'])),
                'hora_fin'    => date('H:i:s', strtotime($datos['fecha_hora'] . ' + 2 hours')),
                'observacion' => 'Generado automáticamente por cita médica en dispensario institucional.',
                'estado'      => 'pendiente',
                'vence_en'    => now()->addHours(72), // Simplificado para este contexto
            ]);

            DB::commit();

            return ['cita_id' => $citaId, 'permiso_generado' => $permiso->id];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function obtenerMiHistoriaClinicaBasica(int $servidorId): array
    {
        // SOLO datos básicos: fecha, médico, diagnóstico. NADA CLÍNICO DETALLADO.
        return DB::table('consultas_medicas')
            ->join('users as medicos', 'consultas_medicas.medico_id', '=', 'medicos.id')
            ->where('consultas_medicas.servidor_id', $servidorId)
            ->select('consultas_medicas.fecha', 'medicos.name as medico', 'consultas_medicas.diagnostico_cie10')
            ->orderBy('consultas_medicas.fecha', 'desc')
            ->get()
            ->toArray();
    }
}
