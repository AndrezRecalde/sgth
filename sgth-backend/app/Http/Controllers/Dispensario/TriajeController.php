<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreTriajeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\Triaje;
use App\Services\Dispensario\TriajeService;
use Illuminate\Http\JsonResponse;

class TriajeController extends Controller
{
    public function __construct(
        private readonly TriajeService $triajeService
    ) {}

    public function store(
        StoreTriajeRequest $request,
        int $agendaId
    ): JsonResponse {
        $triaje = $this->triajeService->registrar(
            $agendaId,
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::created(
            $triaje, 'Triaje registrado exitosamente.'
        );
    }

    /** La toma vigente del turno, que es la última registrada. */
    public function show(int $agendaId): JsonResponse
    {
        $triaje = Triaje::where('agenda_medica_id', $agendaId)
            ->latest('id')
            ->firstOrFail();

        return ApiResponse::ok($triaje);
    }

    /**
     * Todas las tomas del turno, de la más antigua a la más reciente, con quién
     * las registró. Es lo que permite ver que una lectura se corrigió y con qué
     * cifras estaba antes.
     */
    public function historial(int $agendaId): JsonResponse
    {
        $tomas = Triaje::where('agenda_medica_id', $agendaId)
            // `email` entra en el select aunque no se muestre: el accesor
            // `nombre_completo` de User cae a él cuando el usuario no tiene
            // servidor, y si no se cargó devuelve null contra su propio tipo
            // declarado. El servidor va por lo mismo, para el caso normal.
            ->with([
                'enfermera:id,usuario_ti,email,servidor_id',
                'enfermera.servidor:id,nombre,apellido',
            ])
            ->orderBy('id')
            ->get();

        return ApiResponse::ok($tomas);
    }

    public function ultimoPorAgenda(int $agendaId): JsonResponse
    {
        $agenda = AgendaMedica::findOrFail($agendaId);

        $historiaClinicaId = $this->resolverHistoriaClinicaId($agenda);

        if (!$historiaClinicaId) {
            return ApiResponse::ok(null);
        }

        $ultimoTriaje = Triaje::where(
            'historia_clinica_id', $historiaClinicaId
        )
            ->where('agenda_medica_id', '!=', $agendaId)
            ->orderBy('registrado_en', 'desc')
            ->first();

        return ApiResponse::ok($ultimoTriaje);
    }

    /**
     * Solo lectura: devuelve null si el paciente no tiene historia. Registrar
     * un triaje sí la abre, pero consultar el último no debe crear nada.
     */
    private function resolverHistoriaClinicaId(
        AgendaMedica $agenda
    ): ?int {
        if ($agenda->servidor_id) {
            return \App\Models\Dispensario\HistoriaClinica::where(
                'servidor_id', $agenda->servidor_id
            )->value('id');
        }

        if ($agenda->carga_familiar_id) {
            return \App\Models\Dispensario\HistoriaClinica::where(
                'carga_familiar_id', $agenda->carga_familiar_id
            )->value('id');
        }

        return null;
    }

    public function pendientes(): JsonResponse
    {
        $turnos = AgendaMedica::with([
            'medico', 'servidor', 'cargaFamiliar.servidor',
        ])->where('estado', 'en_espera')
          ->where('requiere_triaje', true)
          // Sobre `triajes` y no sobre `triaje`: el segundo es ahora una
          // subconsulta «la última», y preguntarle si no existe no es lo mismo
          // que preguntar si el turno no tiene ninguna toma.
          ->whereDoesntHave('triajes')
          // Solo los de hoy: un turno de ayer que nadie cerró se quedaba en
          // esta lista para siempre, y no se le puede tomar el triaje.
          ->whereDate('fecha', today())
          ->orderBy('registrado_en', 'asc')
          ->orderBy('id')
          ->get();

        return ApiResponse::ok($turnos);
    }
}
