<?php
namespace App\Http\Controllers\Asistencia;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MarcacionController extends Controller
{
    /**
     * Consulta marcaciones desde SQLSERVER usando SP.
     * Parámetros: servidor_id, fecha_inicio, fecha_fin
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'codigo_marcacion' => 'required|string',
            'fecha_inicio'     => 'required|date',
            'fecha_fin'        => 'required|date|after_or_equal:fecha_inicio',
        ]);

        try {
            $marcaciones = DB::connection('sqlsrv')
                ->select('EXEC sp_GetMarcacionesPorDiaYTipo_v3 ?, ?, ?', [
                    $request->codigo_marcacion,
                    $request->fecha_inicio,
                    $request->fecha_fin,
                ]);

            return ApiResponse::ok(
                $marcaciones,
                'Marcaciones consultadas correctamente.'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Error al consultar marcaciones: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Registra una marcación online en SQLSERVER.
     * Para servidores en campo/territorio.
     */
    public function registrarOnline(Request $request): JsonResponse
    {
        $request->validate([
            'codigo_marcacion' => 'required|string',
            'checktype'        => 'required|in:I,O',
            'latitud'          => 'nullable|numeric',
            'longitud'         => 'nullable|numeric',
        ]);

        try {
            $user = DB::connection('sqlsrv')
                ->table('USERINFO')
                ->where('BADGENUMBER', $request->codigo_marcacion)
                ->first();

            if (!$user) {
                return ApiResponse::error(
                    'El servidor no tiene registro en el sistema biométrico.',
                    404
                );
            }

            DB::connection('sqlsrv')->table('CHECKINOUT')->insert([
                'USERID'        => $user->USERID,
                'CHECKTIME'     => DB::connection('sqlsrv')->raw(
                    "CONVERT(DATETIME, '" .
                    Carbon::now()->format('Y-m-d H:i:s') .
                    "', 120)"
                ),
                'CHECKTYPE'     => $request->checktype,
                'VERIFYCODE'    => 1,
                'SENSORID'      => 4,
                'Memoinfo'      => null,
                'WorkCode'      => null,
                'sn'            => null,
                'UserExtFmt'    => 1,
                'VERIFYAPPROVE' => null,
                'GEOLT'         => $request->longitud,
                'GEOLG'         => $request->latitud,
                'MARCTYPE'      => 'IR',
                'EDITADA'       => 0,
            ]);

            return ApiResponse::ok(
                null,
                'Marcación registrada correctamente.'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Error al registrar marcación: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Verifica si el servidor ya marcó hoy.
     */
    public function estadoHoy(Request $request): JsonResponse
    {
        $request->validate([
            'codigo_marcacion' => 'required|string',
        ]);

        try {
            $hoy = Carbon::now()->toDateString();
            $marcaciones = DB::connection('sqlsrv')
                ->select('EXEC sp_GetMarcacionesPorDiaYTipo_v3 ?, ?, ?', [
                    $request->codigo_marcacion,
                    $hoy,
                    $hoy,
                ]);

            $marcacionHoy = !empty($marcaciones)
                ? $marcaciones[0] : null;

            return ApiResponse::ok(
                $marcacionHoy,
                'Estado de marcación del día.'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Error al consultar estado: ' . $e->getMessage(),
                500
            );
        }
    }
}
