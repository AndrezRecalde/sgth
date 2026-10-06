<?php
namespace App\Http\Controllers\Asistencia;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\Servidor;
use App\Services\Asistencia\MarcacionBiometricaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/*
| Los errores pasan el código HTTP con nombre (`codigo:`). Antes iba como
| segundo argumento posicional, que es `errores`: todas las respuestas de
| error de este controlador salían con 422, fuera un 404, un 403 o un 503.
*/
class MarcacionController extends Controller
{
    /**
     * Consultar marcaciones de un servidor por cédula.
     * Solo servidores con puede_marcar = true.
     *
     * Si el biométrico rechaza la cédula (de relleno, repartida en varios
     * usuarios…) el procedimiento lanza una ReglaNegocioException, que sale
     * como 422 con su mensaje.
     */
    public function index(Request $request, MarcacionBiometricaService $biometrico): JsonResponse
    {
        $request->validate([
            'cedula'       => 'required|string',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $cedula = $request->cedula;

        // Verificar que el servidor puede marcar
        $servidor = Servidor::where('cedula', $cedula)
            ->where('puede_marcar', true)
            ->first();

        if (!$servidor) {
            return ApiResponse::error(
                'El servidor no existe o no tiene habilitada la marcación biométrica.',
                codigo: 404
            );
        }

        try {
            $marcaciones = $biometrico->porCedula(
                $cedula,
                Carbon::parse($request->fecha_inicio),
                Carbon::parse($request->fecha_fin)
            );
        } catch (\PDOException $e) {
            Log::error('Error consultando marcaciones: ' . $e->getMessage());
            return ApiResponse::error(
                'No se pudo conectar al sistema biométrico.',
                codigo: 503
            );
        }

        return ApiResponse::ok(
            $marcaciones,
            'Marcaciones obtenidas correctamente.'
        );
    }

    /**
     * Estado de marcación del día para el usuario autenticado.
     * Usa la cédula del servidor vinculado al usuario.
     *
     * El procedimiento devuelve la fila de hoy aunque no haya marcaciones si
     * el día tiene horario o permiso; sin nada de eso, `datos` es null.
     */
    public function estadoHoy(Request $request, MarcacionBiometricaService $biometrico): JsonResponse
    {
        $user    = $request->user();
        $cedula  = $user->servidor?->cedula ?? null;

        if (!$cedula) {
            return ApiResponse::error(
                'Tu usuario no tiene un servidor vinculado.',
                codigo: 404
            );
        }

        // Verificar que puede marcar
        if (!($user->servidor?->puede_marcar ?? false)) {
            return ApiResponse::error(
                'Tu perfil no tiene habilitada la marcación biométrica.',
                codigo: 403
            );
        }

        try {
            $marcaciones = $biometrico->porCedula($cedula, Carbon::today(), Carbon::today());
        } catch (\PDOException $e) {
            Log::error('Error estado hoy: ' . $e->getMessage());
            return ApiResponse::error(
                'No se pudo obtener el estado del día.',
                codigo: 503
            );
        }

        return ApiResponse::ok(
            $marcaciones[0] ?? null,
            'Estado de marcación del día.'
        );
    }

    /**
     * Registrar marcación online.
     * Usa la cédula del usuario autenticado.
     * Solo si puede_marcar = true.
     */
    public function registrarOnline(Request $request, MarcacionBiometricaService $biometrico): JsonResponse
    {
        $request->validate([
            'checktype' => 'required|in:I,O',
            'latitud'   => 'nullable|numeric',
            'longitud'  => 'nullable|numeric',
        ]);

        $user   = $request->user();
        $cedula = $user->servidor?->cedula ?? null;

        if (!$cedula) {
            return ApiResponse::error(
                'Tu usuario no tiene un servidor vinculado.',
                codigo: 404
            );
        }

        if (!($user->servidor?->puede_marcar ?? false)) {
            return ApiResponse::error(
                'Tu perfil no tiene habilitada la marcación biométrica.',
                codigo: 403
            );
        }

        try {
            $registrada = $biometrico->registrarMarcacion($cedula, $request->checktype, now());
        } catch (\PDOException $e) {
            Log::error('Error marcación online: ' . $e->getMessage());
            return ApiResponse::error(
                'No se pudo registrar la marcación.',
                codigo: 503
            );
        }

        if (!$registrada) {
            return ApiResponse::error(
                'No se encontró el registro biométrico para esta cédula.',
                codigo: 404
            );
        }

        return ApiResponse::ok(
            null,
            'Marcación registrada correctamente.'
        );
    }
}
