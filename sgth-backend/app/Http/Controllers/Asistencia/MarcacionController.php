<?php
namespace App\Http\Controllers\Asistencia;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\Servidor;
use App\Models\User;
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
     *
     * Las de cualquier servidor, quien tiene `ver-asistencia-todos` (Talento
     * Humano, máxima autoridad, auditoría: la misma regla con la que se ven
     * las vacaciones de toda la institución). Los demás, solo las propias.
     * Antes bastaba con iniciar sesión para leer las de cualquier cédula.
     *
     * Talento Humano ve también el historial de quien ya no marca o ya no
     * está activo (decisión del 2026-10-06): antes se exigía `puede_marcar`,
     * y quitarle la marcación a alguien escondía todas sus marcaciones
     * pasadas. A uno mismo se le sigue exigiendo tenerla habilitada.
     *
     * El permiso se comprueba antes de buscar al servidor, para que un 404
     * no le diga a quien no puede consultar qué cédulas existen.
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

        $cedula  = $request->cedula;
        $user    = $request->user();
        $veTodos = $user->can(Permiso::VER_ASISTENCIA_TODOS->value);

        if (!$veTodos && $cedula !== $user->servidor?->cedula) {
            return ApiResponse::noAutorizado(
                'Solo puede consultar sus propias marcaciones.'
            );
        }

        $existe = Servidor::where('cedula', $cedula)
            ->when(!$veTodos, fn ($q) => $q->where('puede_marcar', true))
            ->exists();

        if (!$existe) {
            return ApiResponse::error(
                $veTodos
                    ? 'No hay un servidor con esa cédula en el SGTH.'
                    : 'Su perfil no tiene habilitada la marcación biométrica.',
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
        $user = $request->user();

        if ($rechazo = $this->rechazoParaMarcar($user)) {
            return $rechazo;
        }

        try {
            $marcaciones = $biometrico->porCedula($user->servidor->cedula, Carbon::today(), Carbon::today());
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
     * Registrar marcación online, a nombre del usuario autenticado.
     *
     * Exige `marcar-en-linea`, que TI asigna persona por persona a pedido de
     * Talento Humano; antes bastaba con `puede_marcar` y se marcaba desde
     * cualquier lugar. El permiso se comprueba primero, antes incluso de
     * validar la petición: a quien no lo tiene no se le dice nada más.
     *
     * La ubicación es obligatoria (decisión del 2026-10-06) y se guarda en el
     * biométrico. El procedimiento también la exige, porque es la única
     * puerta de escritura del SGTH.
     */
    public function registrarOnline(Request $request, MarcacionBiometricaService $biometrico): JsonResponse
    {
        $user = $request->user();

        if (!$user->can(Permiso::MARCAR_EN_LINEA->value)) {
            return ApiResponse::noAutorizado(
                'No tiene autorización para marcar en línea. Solicítela a Talento Humano.'
            );
        }

        $request->validate([
            'checktype' => 'required|in:I,O',
            'latitud'   => 'required|numeric|between:-90,90',
            'longitud'  => 'required|numeric|between:-180,180',
        ]);

        if ($rechazo = $this->rechazoParaMarcar($user)) {
            return $rechazo;
        }

        try {
            $registrada = $biometrico->registrarMarcacion(
                $user->servidor->cedula,
                $request->checktype,
                now(),
                (float) $request->latitud,
                (float) $request->longitud,
            );
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

    /**
     * Lo que impide marcar a nombre propio, o null si nada lo impide: no tener
     * servidor vinculado, no tener la marcación habilitada o que el servidor
     * esté inactivo. Antes no se miraba el estado: un servidor inactivo con
     * `puede_marcar` seguía marcando.
     */
    private function rechazoParaMarcar(User $user): ?JsonResponse
    {
        $servidor = $user->servidor;

        if (!$servidor?->cedula) {
            return ApiResponse::error(
                'Su usuario no tiene un servidor vinculado.',
                codigo: 404
            );
        }

        if (!$servidor->puede_marcar) {
            return ApiResponse::error(
                'Su perfil no tiene habilitada la marcación biométrica.',
                codigo: 403
            );
        }

        if (!$servidor->estado) {
            return ApiResponse::error(
                'El servidor vinculado a su usuario está inactivo.',
                codigo: 403
            );
        }

        return null;
    }
}
