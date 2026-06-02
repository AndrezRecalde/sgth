<?php
namespace App\Http\Controllers\Asistencia;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarcacionController extends Controller
{
    /**
     * Consultar marcaciones de un servidor por cédula.
     * Solo servidores con puede_marcar = true.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'cedula'       => 'required|string',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $cedula      = $request->cedula;
        $fechaInicio = Carbon::parse($request->fecha_inicio)
            ->format('Y-m-d');
        $fechaFin    = Carbon::parse($request->fecha_fin)
            ->format('Y-m-d');

        // Verificar que el servidor puede marcar
        $servidor = \App\Models\Expediente\Servidor::where('cedula', $cedula)
            ->where('puede_marcar', true)
            ->first();

        if (!$servidor) {
            return ApiResponse::error(
                'El servidor no existe o no tiene habilitada la marcación biométrica.',
                404
            );
        }

        try {
            $marcaciones = DB::connection('sqlsrv')
                ->select(
                    'EXEC sp_GetMarcacionesPorDiaYTipo_v3 ?, ?, ?',
                    [$cedula, $fechaInicio, $fechaFin]
                );

            return ApiResponse::ok(
                $marcaciones,
                'Marcaciones obtenidas correctamente.'
            );
        } catch (\Exception $e) {
            Log::error('Error consultando marcaciones: ' . $e->getMessage());
            return ApiResponse::error(
                'No se pudo conectar al sistema biométrico.',
                503
            );
        }
    }

    /**
     * Estado de marcación del día para el usuario autenticado.
     * Usa la cédula del servidor vinculado al usuario.
     */
    public function estadoHoy(Request $request): JsonResponse
    {
        $user    = $request->user();
        $cedula  = $user->servidor?->cedula ?? null;

        if (!$cedula) {
            return ApiResponse::error(
                'Tu usuario no tiene un servidor vinculado.',
                404
            );
        }

        // Verificar que puede marcar
        if (!($user->servidor?->puede_marcar ?? false)) {
            return ApiResponse::error(
                'Tu perfil no tiene habilitada la marcación biométrica.',
                403
            );
        }

        $hoy = Carbon::now()->format('Y-m-d');

        try {
            $marcaciones = DB::connection('sqlsrv')
                ->select(
                    'EXEC sp_GetMarcacionesPorDiaYTipo_v3 ?, ?, ?',
                    [$cedula, $hoy, $hoy]
                );

            $estado = !empty($marcaciones) ? $marcaciones[0] : null;

            return ApiResponse::ok(
                $estado,
                'Estado de marcación del día.'
            );
        } catch (\Exception $e) {
            Log::error('Error estado hoy: ' . $e->getMessage());
            return ApiResponse::error(
                'No se pudo obtener el estado del día.',
                503
            );
        }
    }

    /**
     * Registrar marcación online.
     * Usa la cédula del usuario autenticado.
     * Solo si puede_marcar = true.
     */
    public function registrarOnline(Request $request): JsonResponse
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
                404
            );
        }

        if (!($user->servidor?->puede_marcar ?? false)) {
            return ApiResponse::error(
                'Tu perfil no tiene habilitada la marcación biométrica.',
                403
            );
        }

        try {
            // Obtener USERID de USERINFO por SSN (cédula)
            $userInfo = DB::connection('sqlsrv')
                ->select(
                    'SELECT USERID FROM USERINFO WHERE SSN = ?',
                    [$cedula]
                );

            if (empty($userInfo)) {
                return ApiResponse::error(
                    'No se encontró el registro biométrico para esta cédula.',
                    404
                );
            }

            $userId = $userInfo[0]->USERID;

            // Registrar en CHECKINOUT
            DB::connection('sqlsrv')->statement(
                'INSERT INTO CHECKINOUT
                    (USERID, CHECKTIME, CHECKTYPE, SENSORID, MARCTYPE)
                 VALUES (?, ?, ?, 4, ?)',
                [
                    $userId,
                    now()->format('Y-m-d H:i:s'),
                    $request->checktype,
                    'IR',
                ]
            );

            return ApiResponse::ok(
                null,
                'Marcación registrada correctamente.'
            );
        } catch (\Exception $e) {
            Log::error('Error marcación online: ' . $e->getMessage());
            return ApiResponse::error(
                'No se pudo registrar la marcación.',
                503
            );
        }
    }
}
