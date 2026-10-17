<?php

namespace App\Http\Controllers\Expediente;

use App\Enums\EstadoFormacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\RechazarFormacionRequest;
use App\Http\Requests\Expediente\StoreHistorialAcademicoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\HistorialAcademicoServidor;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\FormacionAcademicaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistorialAcademicoController extends Controller
{
    public function __construct(private readonly FormacionAcademicaService $formacion) {}

    public function index(int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);
        $historial = $servidor->historialAcademico()
            ->orderByDesc('fecha_inicio')->orderByDesc('id')
            ->get();
        return ApiResponse::ok($historial, 'Historial académico del servidor.');
    }

    /** Lo que registra Talento Humano entra validado: lo revisó quien lo registra. */
    public function store(
        StoreHistorialAcademicoRequest $request,
        int $servidorId
    ): JsonResponse {
        Servidor::findOrFail($servidorId);
        $registro = HistorialAcademicoServidor::create([
            ...$request->validated(),
            'servidor_id'    => $servidorId,
            'estado'         => EstadoFormacion::VALIDADO,
            'registrado_por' => $request->user()->id,
        ]);
        return ApiResponse::created($registro, 'Registro académico agregado.');
    }

    public function update(
        StoreHistorialAcademicoRequest $request,
        int $servidorId,
        int $id
    ): JsonResponse {
        $registro = HistorialAcademicoServidor::where('servidor_id', $servidorId)
            ->findOrFail($id);
        $registro->update($request->validated());
        return ApiResponse::ok($registro, 'Registro académico actualizado.');
    }

    public function destroy(int $servidorId, int $id): JsonResponse
    {
        $registro = HistorialAcademicoServidor::where('servidor_id', $servidorId)
            ->findOrFail($id);
        $registro->delete();
        return ApiResponse::ok(null, 'Registro académico eliminado.');
    }

    /** Los títulos y cursos que los servidores registraron desde el portal. */
    public function porValidar(): JsonResponse
    {
        return ApiResponse::ok($this->formacion->porValidar(), 'Formación por validar.');
    }

    public function validar(Request $request, int $servidorId, int $id): JsonResponse
    {
        $registro = $this->formacion->validar($this->registro($servidorId, $id), $request->user());

        return ApiResponse::ok($registro, 'Validado: ya consta en la hoja de vida del servidor.');
    }

    public function rechazar(RechazarFormacionRequest $request, int $servidorId, int $id): JsonResponse
    {
        $registro = $this->formacion->rechazar(
            $this->registro($servidorId, $id),
            $request->user(),
            $request->validated('motivo'),
        );

        return ApiResponse::ok($registro, 'Rechazado: el servidor verá el motivo en su perfil.');
    }

    public function documento(int $servidorId, int $id): StreamedResponse
    {
        return $this->formacion->descargar($this->registro($servidorId, $id));
    }

    private function registro(int $servidorId, int $id): HistorialAcademicoServidor
    {
        return HistorialAcademicoServidor::where('servidor_id', $servidorId)->findOrFail($id);
    }
}
