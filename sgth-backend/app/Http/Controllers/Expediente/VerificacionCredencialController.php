<?php

namespace App\Http\Controllers\Expediente;

use App\Contracts\Comunicacion\CredencialServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Comprueba que una credencial es auténtica y está vigente
 * (docs/diseno-comunicacion-social.md, 6.4). Pública y sin sesión: la usa
 * quien escanea el QR del reverso, en una puerta o en un operativo.
 *
 * Es la misma superficie que la verificación del certificado laboral, y se
 * protege igual: el código es aleatorio de 40 caracteres —ver
 * `CredencialService::generarCodigo()`— y la ruta va limitada por IP.
 *
 * Qué se enseña [D9]: si está vigente, la foto, el nombre, el cargo y la
 * unidad **actuales**, para cotejar con la persona que se tiene delante;
 * nunca la cédula, el tipo de sangre ni el contacto de emergencia. Si no lo
 * está, solo eso: una tarjeta perdida no dice de quién era.
 */
final class VerificacionCredencialController extends Controller
{
    public function __invoke(string $codigo, CredencialServiceInterface $credenciales): JsonResponse
    {
        $resultado = $credenciales->verificar($codigo);

        if ($resultado === null) {
            return ApiResponse::error('No existe ninguna credencial con ese código de verificación.', null, 404);
        }

        return ApiResponse::ok(
            $resultado,
            $resultado['vigente'] ? 'Credencial vigente.' : 'Esta credencial no está vigente.',
        );
    }
}
