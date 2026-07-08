<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\ResultadoMedico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class ResultadoMedicoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ResultadoMedico::with(['subidoPor:id,nombre,apellido'])
            ->orderBy('fecha_resultado', 'desc');

        if ($request->filled('historia_clinica_id')) {
            $query->where(
                'historia_clinica_id',
                $request->integer('historia_clinica_id')
            );
        }

        if ($request->filled('consulta_medica_id')) {
            $query->where(
                'consulta_medica_id',
                $request->integer('consulta_medica_id')
            );
        }

        return ApiResponse::ok($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'historia_clinica_id' => ['required', 'integer', 'exists:historias_clinicas,id'],
            'consulta_medica_id'  => ['nullable', 'integer', 'exists:consultas_medicas,id'],
            'tipo'                => ['required', Rule::in([
                'laboratorio', 'imagen', 'ecografia',
                'rayos_x', 'electrocardiograma', 'otro',
            ])],
            'descripcion'         => ['required', 'string', 'max:500'],
            'fecha_resultado'     => ['required', 'date'],
            'archivo'             => ['required', 'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],
        ]);

        $ruta = $request->file('archivo')->store(
            'resultados-medicos', 'public'
        );

        $resultado = ResultadoMedico::create([
            'historia_clinica_id' => $request->integer('historia_clinica_id'),
            'consulta_medica_id'  => $request->integer('consulta_medica_id') ?: null,
            'subido_por'          => $request->user()->id,
            'tipo'                => $request->input('tipo'),
            'descripcion'         => $request->input('descripcion'),
            'fecha_resultado'     => $request->input('fecha_resultado'),
            'archivo'             => $ruta,
        ]);

        return ApiResponse::created(
            $resultado->load('subidoPor:id,nombre,apellido'),
            'Resultado médico registrado correctamente.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $resultado = ResultadoMedico::findOrFail($id);
        Storage::disk('public')->delete($resultado->archivo);
        $resultado->delete();

        return ApiResponse::ok([], 'Resultado eliminado correctamente.');
    }
}
