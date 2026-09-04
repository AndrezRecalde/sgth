<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreTriajeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\Triaje;
use App\Services\Dispensario\ValoracionSignosVitales;
use Illuminate\Http\JsonResponse;

class TriajeController extends Controller
{
    public function store(
        StoreTriajeRequest $request,
        int $agendaId
    ): JsonResponse {
        $agenda = AgendaMedica::findOrFail($agendaId);

        $datos = $request->validated();

        // Calcular IMC con peso y talla
        $tallaMetros = $datos['talla_cm'] / 100;
        $imc = $tallaMetros > 0
            ? round($datos['peso_kg'] / ($tallaMetros ** 2), 2)
            : null;

        // La valoración se guarda con el triaje: la cola y el historial deben
        // mostrar lo que se valoró con estas cifras, no lo que diría la tabla
        // de umbrales el día que alguien consulte el registro.
        $valoracion = ValoracionSignosVitales::evaluar(
            $datos,
            $this->edadDelPaciente($agenda)
        );

        $triaje = Triaje::updateOrCreate(
            ['agenda_medica_id' => $agenda->id],
            [
                ...$datos,
                'agenda_medica_id'    => $agenda->id,
                'historia_clinica_id' => $this->resolverHistoriaClinicaId($agenda),
                'enfermera_id'        => $request->user()->id,
                'imc'                 => $imc,
                'nivel_alerta'        => $valoracion['nivel'],
                'hallazgos_alerta'    => $valoracion['hallazgos'],
                'registrado_en'       => now(),
            ]
        );

        if ($agenda->estado === 'en_espera') {
            $agenda->update(['estado' => 'en_sala']);
        }

        return ApiResponse::created(
            $triaje, 'Triaje registrado exitosamente.'
        );
    }

    public function show(int $agendaId): JsonResponse
    {
        $triaje = Triaje::where(
            'agenda_medica_id', $agendaId
        )->firstOrFail();

        return ApiResponse::ok($triaje);
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
     * Edad del paciente del turno, sea servidor o carga familiar. Sin fecha de
     * nacimiento devuelve null, y entonces se valora como adulto: es lo que
     * más se parece a la población que atiende el dispensario.
     */
    private function edadDelPaciente(AgendaMedica $agenda): ?int
    {
        $nacimiento = $agenda->servidor_id
            ? $agenda->servidor?->fecha_nacimiento
            : $agenda->cargaFamiliar?->fecha_nacimiento;

        return $nacimiento?->age;
    }

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
          ->whereDoesntHave('triaje')
          ->orderBy('registrado_en', 'asc')
          ->get();

        return ApiResponse::ok($turnos);
    }
}
