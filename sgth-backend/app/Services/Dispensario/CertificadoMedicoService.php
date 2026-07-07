<?php

namespace App\Services\Dispensario;

use App\Enums\EstadoPermiso;
use App\Enums\TipoPermiso;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Dispensario\CertificadoMedico;
use App\Models\Dispensario\ConsultaMedica;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CertificadoMedicoService
{
    public const DIAS_MAX_REPOSO = 3;

    public function emitir(array $datos, int $emisorId): CertificadoMedico
    {
        return DB::transaction(function () use ($datos, $emisorId) {
            $dias = (int) $datos['dias_reposo'];

            if ($dias < 1 || $dias > self::DIAS_MAX_REPOSO) {
                throw new ReglaNegocioException(
                    'El máximo de días de reposo que puede ' .
                    'otorgar un médico es de ' .
                    self::DIAS_MAX_REPOSO . ' días.'
                );
            }

            $consulta = ConsultaMedica::with('historiaClinica')
                ->findOrFail($datos['consulta_medica_id']);

            $historia = $consulta->historiaClinica;
            $esServidor = $historia && $historia->servidor_id;

            $fechaInicio = Carbon::parse(
                $datos['fecha_inicio'] ?? $consulta->fecha_consulta
            );
            $fechaFin = isset($datos['fecha_fin'])
                ? Carbon::parse($datos['fecha_fin'])
                : $fechaInicio->copy()->addDays($dias - 1);

            $diasCalculados = $fechaInicio->diffInDays($fechaFin) + 1;
            if ($diasCalculados > self::DIAS_MAX_REPOSO) {
                throw new ReglaNegocioException(
                    'El rango seleccionado excede el máximo de ' .
                    self::DIAS_MAX_REPOSO . ' días de reposo.'
                );
            }

            $permisoId = null;
            $folio     = null;

            // Solo genera permiso de asistencia si el
            // paciente es el servidor titular (no familiar)
            if ($esServidor) {
                $permiso = $this->crearPermisoAutomatico(
                    $historia->servidor_id,
                    $fechaInicio,
                    $emisorId,
                    $datos['observaciones'] ?? null,
                );
                $permisoId = $permiso->id;
                $folio     = $permiso->folio;
            } else {
                $folio = $this->generarFolioCertificado();
            }

            $certificado = CertificadoMedico::create([
                'consulta_medica_id'   => $consulta->id,
                'emitido_por'          => $emisorId,
                'dias_reposo'          => $dias,
                'fecha_inicio'         => $fechaInicio,
                'fecha_fin'            => $fechaFin,
                'diagnostico_cie10_id' => $datos['diagnostico_cie10_id'] ?? null,
                'observaciones'        => $datos['observaciones'] ?? null,
                'permiso_servidor_id'  => $permisoId,
                'folio'                => $folio,
                'tipo_paciente'        => $esServidor ? 'servidor' : 'beneficiario',
                'created_by'           => $emisorId,
            ]);

            return $certificado->load([
                'consultaMedica',
                'emisor',
                'diagnosticoCie10',
                'permisoServidor',
            ]);
        });
    }

    private function crearPermisoAutomatico(
        int $servidorId,
        Carbon $fecha,
        int $emisorId,
        ?string $observacion,
    ): PermisoServidor {
        $anioActual     = $fecha->year;
        $cantidadActual = PermisoServidor::whereYear(
            'created_at', $anioActual
        )->count();
        $secuencial = str_pad(
            $cantidadActual, 5, '0', STR_PAD_LEFT
        );
        $folio = "PER-{$anioActual}-{$secuencial}";

        return PermisoServidor::create([
            'servidor_id' => $servidorId,
            'tipo'        => TipoPermiso::ENFERMEDAD->value,
            'fecha'       => $fecha,
            'hora_inicio' => '00:00',
            'hora_fin'    => '23:59',
            'observacion' => $observacion
                ?? 'Certificado médico emitido por el dispensario.',
            // Activo directo: el médico es fuente
            // confiable, se salta confirmación de Recepción
            'estado'        => EstadoPermiso::ACTIVO->value,
            'folio'         => $folio,
            'confirmado_por' => $emisorId,
            'confirmado_en'  => now(),
            'creado_por'     => $emisorId,
        ]);
    }

    private function generarFolioCertificado(): string
    {
        $anioActual     = date('Y');
        $cantidadActual = CertificadoMedico::whereYear(
            'created_at', $anioActual
        )->count();
        $secuencial = str_pad(
            $cantidadActual, 5, '0', STR_PAD_LEFT
        );
        return "CERT-{$anioActual}-{$secuencial}";
    }
}
