<?php

namespace App\Http\Controllers\Autoservicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Autoservicio\StoreMiFormacionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\HistorialAcademicoServidor;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\FormacionAcademicaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La formación académica de «Mi perfil»: la propia, con su estado, y los
 * títulos o cursos que el servidor registra para que los valide Talento
 * Humano. Siempre los del propio servidor.
 */
final class MiFormacionController extends Controller
{
    public function __construct(private readonly FormacionAcademicaService $formacion) {}

    public function index(Request $request): JsonResponse
    {
        $servidorId = $request->user()->servidor_id;

        $registros = $servidorId
            ? HistorialAcademicoServidor::where('servidor_id', $servidorId)
                ->orderByDesc('fecha_inicio')
                ->orderByDesc('id')
                ->get()
            : [];

        return ApiResponse::ok($registros, 'Su formación académica.');
    }

    public function store(StoreMiFormacionRequest $request): JsonResponse
    {
        $servidor = Servidor::find($request->user()->servidor_id);

        if (! $servidor) {
            return ApiResponse::error('Su usuario no está vinculado a un expediente.', null, 422);
        }

        $datos = collect($request->validated())->except('documento')->all();
        $registro = $this->formacion->registrarPropia($servidor, $datos, $request->file('documento'), $request->user());

        return ApiResponse::created($registro, 'Registrado. Talento Humano lo validará con el documento que adjuntó.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->formacion->retirarPropio($this->propio($request, $id));

        return ApiResponse::ok(null, 'Registro retirado.');
    }

    public function documento(Request $request, int $id): StreamedResponse
    {
        return $this->formacion->descargar($this->propio($request, $id));
    }

    /** Uno ajeno responde 404, como si no existiera. */
    private function propio(Request $request, int $id): HistorialAcademicoServidor
    {
        return HistorialAcademicoServidor::where('servidor_id', $request->user()->servidor_id ?? 0)
            ->findOrFail($id);
    }
}
