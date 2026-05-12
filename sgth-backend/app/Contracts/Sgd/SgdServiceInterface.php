<?php

namespace App\Contracts\Sgd;

use App\Models\Sgd\DocumentoInstitucional;

interface SgdServiceInterface
{
    /**
     * Sube y registra un nuevo documento institucional.
     */
    public function subirDocumento(array $datos, $archivo, int $userId): DocumentoInstitucional;

    /**
     * Genera una URL firmada temporalmente para acceder de forma segura al documento físico.
     */
    public function generarUrlFirmada(int $documentoId, int $userId, int $minutosExpiracion = 60): string;

    /**
     * Marca un documento para su publicación en el portal de transparencia (LOTAIP).
     */
    public function publicarLotaip(int $documentoId, int $userId): DocumentoInstitucional;
}
