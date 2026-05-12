<?php

namespace App\Http\Controllers\Asistencia;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\PermisoServidor;

class FolioPermisoController extends Controller
{
    /**
     * Endpoint PÚBLICO sin autenticación para verificación mediante escaneo del QR.
     * Es utilizado por agentes externos o seguridad para validar que el permiso
     * emitido por el sistema existe y no ha sido adulterado.
     */
    public function verificar(string $folio)
    {
        $permiso = PermisoServidor::with(['servidor', 'folioPermiso'])
            ->where('folio', $folio)
            ->first();

        if (!$permiso) {
            return ApiResponse::noEncontrado('El folio escaneado no existe o es inválido.');
        }

        return ApiResponse::ok($permiso, 'Documento validado exitosamente como auténtico e inalterado.');
    }
}
