<?php

namespace App\Contracts\Asistencia;

use App\Models\Asistencia\PermisoServidor;

interface PermisoServiceInterface
{
    /**
     * Crea un nuevo permiso para un servidor aplicando las reglas de negocio:
     * - PERSONAL: máximo 4 horas acumuladas por día, y con saldo vacacional
     *   suficiente, porque se descuenta de él.
     * - OFICIAL: requiere observación obligatoria.
     * - Sin solaparse con otro permiso del mismo servidor ese día.
     * - PERSONAL y OFICIAL se registran desde hoy en adelante, con tolerancia
     *   de 3 días hábiles hacia atrás; ENFERMEDAD y CALAMIDAD, solo hacia atrás.
     * - Vencimiento automático a los 3 días hábiles, feriados incluidos.
     * - Folio único del año.
     */
    public function crear(array $datos, int $servidorId): PermisoServidor;

    /**
     * Confirma la recepción física del documento en Recepción.
     * Pasa el permiso de PENDIENTE a ACTIVO y descuenta el saldo vacacional
     * cuando corresponde, en una sola transacción.
     */
    public function confirmarRecepcion(string $folio, int $recepcionUserId): PermisoServidor;

    /**
     * Valida el documento médico o de calamidad por Trabajo Social.
     * Pasa a estado VALIDADO_TRABAJO_SOCIAL.
     */
    public function validarTrabajoSocial(int $permisoId, int $tsUserId): PermisoServidor;

    /**
     * Recepción rechaza el documento físico: PENDIENTE a RECHAZADO.
     */
    public function rechazar(int $permisoId, int $userId, string $motivo): PermisoServidor;

    /**
     * Deshace una confirmación hecha por error y devuelve al saldo vacacional
     * lo que se hubiera descontado. El permiso vuelve a PENDIENTE.
     */
    public function revertirConfirmacion(int $permisoId, int $userId, string $motivo): PermisoServidor;
}
