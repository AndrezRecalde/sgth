<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreTriajeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\Triaje;
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

        $triaje = Triaje::updateOrCreate(
            ['agenda_medica_id' => $agenda->id],
            [
                ...$datos,
                'agenda_medica_id'    => $agenda->id,
                'historia_clinica_id' => $this->resolverHistoriaClinicaId($agenda),
                'enfermera_id'        => $request->user()->id,
                'imc'                 => $imc,
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
