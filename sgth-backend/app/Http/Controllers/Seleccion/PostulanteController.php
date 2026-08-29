<?php

namespace App\Http\Controllers\Seleccion;

use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\Servidor;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\DocumentoPostulante;
use App\Models\Seleccion\Postulante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class PostulanteController extends Controller
{
    public function index(int $convocatoriaId): JsonResponse
    {
        Convocatoria::findOrFail($convocatoriaId);

        $postulantes = Postulante::with(['evaluacion', 'documentos'])
            ->where('convocatoria_id', $convocatoriaId)
            ->orderBy('apellidos')
            ->get();

        return ApiResponse::ok($postulantes);
    }

    public function store(
        Request $request,
        int $convocatoriaId
    ): JsonResponse {
        $convocatoria = Convocatoria::findOrFail($convocatoriaId);

        if (! in_array($convocatoria->estado->value, ['publicada', 'en_proceso'])) {
            throw new ReglaNegocioException(
                'La convocatoria no está abierta para inscripciones.'
            );
        }

        $esContenedor = (bool) $convocatoria->es_contenedor_permanente;

        $datos = $request->validate([
            // En un contenedor express el puesto lo trae el aspirante; en un
            // concurso formal lo fija la convocatoria y enviarlo es un error.
            'puesto_id' => [
                $esContenedor ? 'required' : 'prohibited',
                'nullable', 'integer', 'exists:puestos,id',
            ],
            'fecha_inscripcion' => ['nullable', 'date'],
            'cedula' => ['required', 'string', 'max:20'],
            'nombres' => ['required', 'string', 'max:150'],
            'segundo_nombre' => ['nullable', 'string', 'max:150'],
            'apellidos' => ['required', 'string', 'max:150'],
            'segundo_apellido' => ['nullable', 'string', 'max:150'],
            'correo' => ['required', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            // Obligatorio desde el 2026-08-28. Al incorporar al aspirante, este
            // valor se copia a `servidores`, donde el género SÍ es requerido;
            // aceptarlo nulo aquí metía por la puerta de atrás un expediente
            // incompleto. Además la ficha FEMO lo usa para decidir qué bloque
            // reproductivo del formulario del MSP mostrar.
            'genero' => ['required', 'string', 'in:masculino,femenino,otro'],
            'estado_civil' => ['nullable', 'string', 'in:soltero,casado,union_libre,divorciado,viudo'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'tipo_sangre' => ['nullable', 'string', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'provincia_nacimiento_id' => ['nullable', 'integer', 'exists:provincias,id'],
            'canton_nacimiento_id' => ['nullable', 'integer', 'exists:cantones,id'],
        ]);

        $datos['fecha_inscripcion'] = $datos['fecha_inscripcion'] ?? now()->toDateString();

        // En un contenedor permanente la unicidad es por año: la misma persona
        // puede ser contratada en 2026 y otra vez en 2027 bajo la misma
        // modalidad. En un concurso formal sigue siendo una sola inscripción.
        $existe = Postulante::where('convocatoria_id', $convocatoriaId)
            ->where('cedula', $datos['cedula'])
            ->when($esContenedor, fn ($q) => $q->whereYear(
                'fecha_inscripcion',
                (int) date('Y', strtotime($datos['fecha_inscripcion']))
            ))
            ->exists();

        if ($existe) {
            throw new ReglaNegocioException(
                $esContenedor
                    ? 'Ya existe un aspirante con esa cédula en esta modalidad para ese año.'
                    : 'Ya existe un postulante con esa cédula en esta convocatoria.'
            );
        }

        // Un servidor activo SÍ puede postular a un concurso interno — no se
        // bloquea la inscripción, solo se marca la referencia para que
        // confirmarIncorporacion() reutilice la identidad existente en vez
        // de intentar crear un Servidor duplicado (violaría la unique de
        // servidores.cedula).
        $servidorExistente = Servidor::where('cedula', $datos['cedula'])->first();

        $postulante = Postulante::create([
            ...$datos,
            'convocatoria_id' => $convocatoriaId,
            'servidor_id' => $servidorExistente?->id,
            'estado' => 'inscrito',
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(
            $postulante, 'Postulante inscrito correctamente.'
        );
    }

    public function show(int $convocatoriaId, int $postulanteId): JsonResponse
    {
        $postulante = Postulante::with(['evaluacion', 'documentos'])
            ->where('convocatoria_id', $convocatoriaId)
            ->findOrFail($postulanteId);

        return ApiResponse::ok($postulante);
    }

    public function update(
        Request $request,
        int $convocatoriaId,
        int $postulanteId
    ): JsonResponse {
        $postulante = Postulante::where('convocatoria_id', $convocatoriaId)
            ->findOrFail($postulanteId);

        $datos = $request->validate([
            'nombres' => ['sometimes', 'string', 'max:150'],
            'apellidos' => ['sometimes', 'string', 'max:150'],
            'correo' => ['sometimes', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'estado' => ['sometimes', Rule::in([
                'inscrito', 'en_evaluacion',
                'seleccionado', 'no_seleccionado', 'lista_espera',
            ])],
        ]);

        $postulante->update([
            ...$datos,
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::ok($postulante, 'Postulante actualizado.');
    }

    public function destroy(
        int $convocatoriaId,
        int $postulanteId
    ): JsonResponse {
        $postulante = Postulante::where('convocatoria_id', $convocatoriaId)
            ->findOrFail($postulanteId);

        $postulante->documentos()->each(function ($doc) {
            Storage::disk('public')->delete($doc->ruta);
        });

        $postulante->delete();

        return ApiResponse::ok([], 'Postulante eliminado.');
    }

    public function subirDocumento(
        Request $request,
        int $convocatoriaId,
        int $postulanteId
    ): JsonResponse {
        $postulante = Postulante::where('convocatoria_id', $convocatoriaId)
            ->findOrFail($postulanteId);

        $request->validate([
            'tipo' => ['required', 'string', 'max:100'],
            'archivo' => ['required', 'file',
                'mimes:pdf,jpg,jpeg,png,doc,docx',
                'max:10240',
            ],
        ]);

        $archivo = $request->file('archivo');
        $ruta = $archivo->store(
            "seleccion/postulantes/{$postulanteId}", 'public'
        );

        $documento = DocumentoPostulante::create([
            'postulante_id' => $postulanteId,
            'tipo' => $request->input('tipo'),
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'ruta' => $ruta,
            'extension' => $archivo->getClientOriginalExtension(),
            'tamano_bytes' => $archivo->getSize(),
        ]);

        return ApiResponse::created(
            $documento, 'Documento subido correctamente.'
        );
    }

    public function eliminarDocumento(
        int $convocatoriaId,
        int $postulanteId,
        int $documentoId
    ): JsonResponse {
        $postulante = Postulante::where('convocatoria_id', $convocatoriaId)
            ->findOrFail($postulanteId);

        $documento = DocumentoPostulante::where('postulante_id', $postulanteId)
            ->findOrFail($documentoId);

        Storage::disk('public')->delete($documento->ruta);
        $documento->delete();

        return ApiResponse::ok([], 'Documento eliminado.');
    }
}
