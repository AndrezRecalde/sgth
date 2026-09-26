<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\EmisionCertificadoLaboral;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Comprueba que un certificado laboral salió de aquí. Pública y sin sesión:
 * la usan el banco o el IESS que reciben el papel.
 *
 * El riesgo de una página así es que alguien recorra códigos para sacar
 * nombres. Contra eso hay dos cosas: el código es aleatorio y no correlativo
 * —ver `CertificadoLaboralService::generarCodigo()`, y el aviso que dejó la
 * verificación pública de permisos, que se retiró justamente por exponer
 * folios correlativos— y la ruta va limitada por IP.
 *
 * Qué se enseña y qué no lo decidió el 2026-09-26 quien mantiene el módulo:
 * lo justo para cotejar el papel que se tiene delante. El puesto y la unidad
 * de un servidor público ya son públicos —el organigrama de esta misma API lo
 * es—, así que van; sirven para detectar la falsificación habitual, que es
 * inflar el cargo o el tiempo de servicio. La cédula va enmascarada: quien
 * tiene el papel puede cotejar los últimos cuatro dígitos sin que la página
 * reparta números de identidad completos. La remuneración no aparece nunca,
 * ni siquiera cuando el certificado se emitió con ella.
 */
final class VerificacionCertificadoController extends Controller
{
    public function __invoke(string $codigo): JsonResponse
    {
        $emision = EmisionCertificadoLaboral::where('codigo', $codigo)->first();

        if (! $emision) {
            return ApiResponse::error(
                'No existe ningún certificado con ese código de verificación.',
                null,
                404,
            );
        }

        $datos = $emision->datos;

        // Un vencido sí se reconoce: al banco le sirve saber que el documento
        // es auténtico pero que caducó, y decidir si pide uno nuevo.
        return ApiResponse::ok([
            'codigo'          => $emision->codigo,
            'documento'       => $emision->tipo->titulo(),
            'nombre_completo' => $datos['nombre_completo'] ?? null,
            'cedula'          => $this->enmascarar($datos['cedula'] ?? null),
            'puesto'          => $datos['cargo_actual'] ?? null,
            'unidad'          => $datos['unidad_actual'] ?? null,
            'anios_servicio'  => $datos['anios_servicio'] ?? null,
            'emitido_en'      => $emision->emitido_en->toDateString(),
            'vence_en'        => $emision->vence_en->toDateString(),
            'vigente'         => $emision->estaVigente(),
            'firmante'        => $emision->firmante_nombre,
            'firmante_cargo'  => $emision->firmante_cargo,
        ], 'Certificado verificado.');
    }

    /** Los últimos cuatro dígitos bastan para cotejar contra el papel. */
    private function enmascarar(?string $cedula): ?string
    {
        if (! $cedula) return null;

        return Str::repeat('•', max(strlen($cedula) - 4, 0)).substr($cedula, -4);
    }
}
