<?php

namespace App\Contracts\Asistencia;

use App\Models\Asistencia\Vacacion;
use App\Models\Expediente\Servidor;
use App\Models\User;

interface VacacionServiceInterface
{
    /**
     * Determina y retorna el motor de cálculo matemático aplicable (LOSEP o Código del Trabajo)
     * basándose en el régimen laboral del servidor.
     */
    public function obtenerMotor(Servidor $servidor): VacacionMotorInterface;

    /**
     * Solicita una nueva vacación.
     * Invoca automáticamente al motor correspondiente para calcular días a descontar
     * (hábiles vs calendario) y verifica que el saldo sea suficiente.
     *
     * @param  array  $datos  ['fecha_inicio', 'fecha_fin']
     */
    public function solicitar(array $datos, int $servidorId): Vacacion;

    /**
     * Aprueba o rechaza una solicitud PENDIENTE; aprobar descuenta los días.
     *
     * @param  'aprobada'|'rechazada'  $nuevoEstado
     */
    public function resolver(int $vacacionId, string $nuevoEstado, User $resolutor): Vacacion;

    /**
     * Anula una solicitud pendiente, o una aprobada que todavía no comenzó;
     * la aprobada devuelve sus días a los períodos de donde salieron.
     *
     * @return array{vacacion: Vacacion, dias_devueltos: float}
     */
    public function anular(int $vacacionId, string $motivo, User $usuario): array;

    /**
     * Calcula el saldo actual de vacaciones del servidor.
     */
    public function calcularSaldoActual(int $servidorId): float;
}
