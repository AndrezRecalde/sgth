<?php

namespace App\Contracts\Asistencia;

use App\Models\Asistencia\PermisoServidor;

interface PermisoServiceInterface
{
    /**
     * Crea un nuevo permiso para un servidor aplicando las reglas de negocio:
     * - PERSONAL: máximo 4 horas.
     * - OFICIAL: requiere observación obligatoria.
     * - Vencimiento automático en 72h laborables.
     * - Generación de Folio Único y QR.
     *
     * @param array $datos
     * @param int $servidorId
     * @return PermisoServidor
     */
    public function crear(array $datos, int $servidorId): PermisoServidor;

    /**
     * Confirma la recepción física del documento en Recepción.
     * Pasa el permiso de PENDIENTE a ACTIVO.
     *
     * @param string $folio
     * @param int $recepcionUserId
     * @return PermisoServidor
     */
    public function confirmarRecepcion(string $folio, int $recepcionUserId): PermisoServidor;

    /**
     * Valida el documento médico o de calamidad por Trabajo Social.
     * Pasa a estado VALIDADO_TRABAJO_SOCIAL.
     *
     * @param int $permisoId
     * @param int $tsUserId
     * @return PermisoServidor
     */
    public function validarTrabajoSocial(int $permisoId, int $tsUserId): PermisoServidor;
}
