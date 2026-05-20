<?php

namespace App\Contracts\Asistencia;

use App\Models\Asistencia\Vacacion;
use App\Models\Expediente\Servidor;

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
     * Calcula el saldo actual de vacaciones del servidor.
     */
    public function calcularSaldoActual(int $servidorId): float;
}
