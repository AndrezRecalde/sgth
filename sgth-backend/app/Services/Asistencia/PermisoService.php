<?php

namespace App\Services\Asistencia;

use App\Contracts\Asistencia\PermisoServiceInterface;
use App\Enums\EstadoPermiso;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermisoService implements PermisoServiceInterface
{
    public function crear(array $datos, int $servidorId): PermisoServidor
    {
        return DB::transaction(function () use ($datos, $servidorId) {
            $tipo = TipoPermiso::tryFrom($datos['tipo']);
            $horaInicio = Carbon::parse($datos['hora_inicio']);
            $horaFin = Carbon::parse($datos['hora_fin']);
            $observacion = $datos['observacion'] ?? null;

            // 1. Validar horas (fecha_fin debe ser mayor a fecha_inicio)
            if ($horaFin->lessThanOrEqualTo($horaInicio)) {
                throw new \Exception("La hora de fin debe ser mayor a la hora de inicio.");
            }

            $diferenciaHoras = $horaInicio->diffInHours($horaFin);

            // 2. REGLA: PERSONAL máximo 4 horas por día
            if ($tipo === TipoPermiso::PERSONAL && $diferenciaHoras > 4) {
                throw new \Exception("Los permisos de tipo PERSONAL no pueden exceder las 4 horas por día.");
            }

            // 3. REGLA: OFICIAL requiere observación obligatoria
            if ($tipo === TipoPermiso::OFICIAL && empty(trim((string)$observacion))) {
                throw new \Exception("La observación es OBLIGATORIA para los permisos de tipo OFICIAL.");
            }

            // 4. Calcular 72h laborables (aprox 3 días hábiles desde la fecha del permiso)
            // Se cuenta desde el inicio de la jornada del día de la incidencia
            $fechaIncidencia = Carbon::parse($datos['fecha']);
            $vencimiento = $fechaIncidencia->copy();
            
            $diasAgregados = 0;
            while ($diasAgregados < 3) {
                $vencimiento->addDay();
                if (!$vencimiento->isWeekend()) {
                    $diasAgregados++;
                }
            }
            $venceEn = $vencimiento->startOfDay(); // Vence al iniciar el 4to día hábil

            // 5. Crear el registro del permiso
            $permiso = PermisoServidor::create([
                'servidor_id' => $servidorId,
                'tipo'        => $tipo->value,
                'fecha'       => $datos['fecha'],
                'hora_inicio' => $datos['hora_inicio'],
                'hora_fin'    => $datos['hora_fin'],
                'observacion' => $observacion,
                'estado'      => EstadoPermiso::PENDIENTE->value,
                'vence_en'    => $venceEn,
            ]);

            // 6. Generar Folio Único secuencial (ej: PER-2026-00045)
            $anioActual = date('Y');
            $cantidadActual = PermisoServidor::whereYear('created_at', $anioActual)->count();
            $secuencial = str_pad($cantidadActual, 5, '0', STR_PAD_LEFT);
            $folioStr = "PER-{$anioActual}-{$secuencial}";

            $permiso->folio = $folioStr;
            $permiso->save();

            // 7. Generar URL pública y QR
            $urlVerificacion = url("/api/v1/permisos/verificar/{$folioStr}");
            // En un entorno real se usaría un paquete como SimpleSoftwareIO/QrCode para generar el PNG físico.
            // Por simplicidad, guardamos la URL directa o el path simulado.
            $qrRuta = "qrs/{$folioStr}.png";

            $permiso->qr_ruta = $qrRuta;
            $permiso->save();

            return $permiso;
        });
    }

    public function confirmarRecepcion(string $folio, int $recepcionUserId): PermisoServidor
    {
        $permiso = PermisoServidor::where('folio', $folio)->firstOrFail();

        if ($permiso->estado !== EstadoPermiso::PENDIENTE) {
            throw new \App\Exceptions\ReglaNegocioException("Solo se pueden confirmar permisos en estado PENDIENTE. Estado actual: {$permiso->estado->value}");
        }

        $permiso->estado = EstadoPermiso::ACTIVO->value;
        $permiso->confirmado_por = $recepcionUserId;
        $permiso->confirmado_en = now();
        $permiso->save();

        return $permiso;
    }

    public function validarTrabajoSocial(int $permisoId, int $tsUserId): PermisoServidor
    {
        $permiso = PermisoServidor::findOrFail($permisoId);

        // Solo aplica para Enfermedad y Calamidad
        if (!in_array($permiso->tipo, [TipoPermiso::ENFERMEDAD, TipoPermiso::CALAMIDAD])) {
            throw new \App\Exceptions\ReglaNegocioException("La validación de Trabajo Social solo aplica para permisos por Enfermedad o Calamidad Doméstica.");
        }

        // Debe estar ACTIVO (ya confirmado por recepción)
        if ($permiso->estado !== EstadoPermiso::ACTIVO) {
            throw new \App\Exceptions\ReglaNegocioException("El permiso debe estar ACTIVO para ser validado por Trabajo Social.");
        }

        $permiso->estado = EstadoPermiso::VALIDADO_TRABAJO_SOCIAL;
        $permiso->validado_ts_por = $tsUserId;
        $permiso->validado_ts_en = now();
        $permiso->save();

        return $permiso;
    }
}
