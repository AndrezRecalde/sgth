<?php

namespace App\Services\Asistencia;

use App\Contracts\Asistencia\VacacionMotorInterface;
use App\Contracts\Asistencia\VacacionServiceInterface;
use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\Vacacion;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Expediente\Servidor;
use App\Services\Asistencia\Motores\VacacionCodigoTrabajoService;
use App\Services\Asistencia\Motores\VacacionLosepService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VacacionService implements VacacionServiceInterface
{
    /**
     * Motor de cálculo según la jurisprudencia aplicable al servidor.
     *
     * Se decide con un `match` sobre los tres regímenes y no descartando el
     * Código del Trabajo: con la forma anterior, el régimen de servicios
     * profesionales —agregado el 2026-08-29— caía por omisión en el motor
     * LOSEP y se le calculaban vacaciones que un contrato civil no genera.
     * Un cuarto régimen tendría que decidir aquí en vez de heredar una rama.
     */
    public function obtenerMotor(Servidor $servidor): VacacionMotorInterface
    {
        $regimen = $servidor->regimen_laboral instanceof RegimenLaboral
            ? $servidor->regimen_laboral
            : RegimenLaboral::tryFrom((string) ($servidor->regimen_laboral ?? 'losep'));

        return match ($regimen) {
            RegimenLaboral::CODIGO_TRABAJO => new VacacionCodigoTrabajoService(),
            RegimenLaboral::LOSEP, null    => new VacacionLosepService(),
            RegimenLaboral::SERVICIOS_PROFESIONALES => throw new ReglaNegocioException(
                'Un contrato de servicios profesionales no genera vacaciones: '
                .'se pacta un entregable, no una jornada.'
            ),
        };
    }

    public function calcularSaldoActual(int $servidorId): float
    {
        $servidor = Servidor::findOrFail($servidorId);

        // Sin derecho a vacaciones no hay saldo que calcular. La comprobación
        // va antes del cálculo legacy de más abajo: ese camino multiplica los
        // días del motor por la antigüedad, así que a un contrato civil le
        // habría inventado un saldo positivo.
        if (! $this->generaVacaciones($servidor)) {
            return 0.0;
        }

        $periodoService = app(PeriodoVacacionService::class);

        // Si no hay períodos generados aún, usar cálculo legacy
        $saldoPeriodos = $periodoService->saldoTotal($servidorId);

        if ($saldoPeriodos > 0) {
            return $saldoPeriodos;
        }

        // Fallback al cálculo anterior
        $motor = $this->obtenerMotor($servidor);

        $fechaIngreso = $servidor->fecha_ingreso_institucion
            ? \Carbon\Carbon::parse($servidor->fecha_ingreso_institucion)
            : now();

        $aniosCompletos = max(1, $fechaIngreso->diffInYears(now()));
        $diasGanadosPorAnio = $motor->calcularDiasGanadosAnuales($servidor);
        $diasAcumulados     = $diasGanadosPorAnio * $aniosCompletos;

        $diasVacaciones = \App\Models\Asistencia\Vacacion::where('servidor_id', $servidorId)
            ->whereIn('estado', ['aprobada', 'gozada'])
            ->sum('dias_solicitados');

        $horasPermisoPersonal = \App\Models\Asistencia\PermisoServidor::where('servidor_id', $servidorId)
            ->where('tipo', 'personal')
            ->whereNotIn('estado', ['anulado', 'pendiente'])
            ->get()
            ->sum(function ($p) {
                $inicio = \Carbon\Carbon::parse($p->hora_inicio);
                $fin    = \Carbon\Carbon::parse($p->hora_fin);
                return $inicio->diffInMinutes($fin) / 60;
            });

        $diasPermiso = round($horasPermisoPersonal / 8, 2);

        return max(0, $diasAcumulados - $diasVacaciones - $diasPermiso);
    }

    /**
     * ¿El régimen de este servidor genera vacaciones?
     *
     * Se pregunta por la capacidad —`RegimenLaboral::generaVacaciones()`— en
     * vez de comparar cadenas en cada sitio.
     */
    private function generaVacaciones(Servidor $servidor): bool
    {
        $regimen = $servidor->regimen_laboral instanceof RegimenLaboral
            ? $servidor->regimen_laboral
            : RegimenLaboral::tryFrom((string) ($servidor->regimen_laboral ?? 'losep'));

        return $regimen?->generaVacaciones() ?? true;
    }

    public function solicitar(array $datos, int $servidorId): Vacacion
    {
        return DB::transaction(function () use ($datos, $servidorId) {
            $servidor = Servidor::findOrFail($servidorId);

            // Se corta aquí y no en el saldo: el mensaje tiene que explicar el
            // motivo —el régimen— y no aparecer como «no tiene días».
            if (! $this->generaVacaciones($servidor)) {
                throw new ReglaNegocioException(
                    'El régimen de este servidor no genera vacaciones.'
                );
            }

            $motor = $this->obtenerMotor($servidor);

            $fechaInicio = Carbon::parse($datos['fecha_inicio']);
            $fechaFin    = Carbon::parse($datos['fecha_fin']);

            if ($fechaFin->lessThan($fechaInicio)) {
                throw new \Exception(
                    'La fecha de fin no puede ser menor a la fecha de inicio.'
                );
            }

            $diasADescontar = $motor->calcularDiasDescuento($fechaInicio, $fechaFin);

            if ($diasADescontar <= 0) {
                throw new \Exception(
                    'Las fechas seleccionadas no representan días laborables descontables.'
                );
            }

            $motivo = \App\Enums\MotivoVacacion::tryFrom($datos['motivo'] ?? '');

            // Solo verificar saldo si el motivo descuenta vacaciones
            if ($motivo?->descuentaVacaciones()) {
                $saldoActual = $this->calcularSaldoActual($servidorId);
                if ($diasADescontar > $saldoActual) {
                    throw new \Exception(
                        "Saldo insuficiente. Intentas solicitar {$diasADescontar} días, ".
                        "pero tu saldo es de {$saldoActual} días."
                    );
                }
            }

            // Determinar tipo_dias según régimen
            $tipoDias = ($servidor->regimen_laboral?->value ?? $servidor->regimen_laboral)
                === 'codigo_trabajo' ? 'calendario' : 'habiles';

            // Crear solicitud
            $vacacion = Vacacion::create([
                'servidor_id'              => $servidorId,
                'unidad_administrativa_id' => $datos['unidad_administrativa_id'] ?? $servidor->unidad_administrativa_id ?? null,
                'jefe_id'                  => $datos['jefe_id'] ?? null,
                'motivo'                   => $datos['motivo'] ?? null,
                'fecha_inicio'             => $fechaInicio,
                'fecha_fin'        => $fechaFin,
                'fecha_retorno'    => $datos['fecha_retorno'] ?? null,
                'fecha_emision'    => $datos['fecha_emision'] ?? now()->toDateString(),
                'dias_solicitados' => $diasADescontar,
                'tipo_dias'        => $tipoDias,
                'estado'           => 'pendiente',
                'creado_por'       => $datos['creado_por'] ?? null,
            ]);

            // Generar folio secuencial: VAC-2026-00001
            $anio        = now()->year;
            $cantidad    = Vacacion::whereYear('created_at', $anio)->count();
            $secuencial  = str_pad($cantidad, 5, '0', STR_PAD_LEFT);
            $folio       = "VAC-{$anio}-{$secuencial}";

            // Generar URL de verificación para QR
            $urlVerificacion = url("/api/v1/asistencia/vacaciones/verificar/{$folio}");

            $vacacion->folio    = $folio;
            $vacacion->codigo_qr = $urlVerificacion;
            $vacacion->save();

            return $vacacion->fresh(['servidor', 'jefe', 'creadoPor']);
        });
    }
}
