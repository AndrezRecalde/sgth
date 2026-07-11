<?php

namespace App\Http\Controllers\Seleccion;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Postulante;
use App\Models\Seleccion\DocumentoPostulante;
use App\Exceptions\ReglaNegocioException;
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

        if (!in_array($convocatoria->estado->value, ['publicada', 'en_proceso'])) {
            throw new ReglaNegocioException(
                'La convocatoria no está abierta para inscripciones.'
            );
        }

        $datos = $request->validate([
            'cedula'    => ['required', 'string', 'max:20'],
            'nombres'   => ['required', 'string', 'max:150'],
            'apellidos' => ['required', 'string', 'max:150'],
            'correo'    => ['required', 'email', 'max:150'],
            'telefono'  => ['nullable', 'string', 'max:20'],
        ]);

        $existe = Postulante::where('convocatoria_id', $convocatoriaId)
            ->where('cedula', $datos['cedula'])
            ->exists();

        if ($existe) {
            throw new ReglaNegocioException(
                'Ya existe un postulante con esa cédula en esta convocatoria.'
            );
        }

        $postulante = Postulante::create([
            ...$datos,
            'convocatoria_id' => $convocatoriaId,
            'estado'          => 'inscrito',
            'created_by'      => $request->user()->id,
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
            'nombres'   => ['sometimes', 'string', 'max:150'],
            'apellidos' => ['sometimes', 'string', 'max:150'],
            'correo'    => ['sometimes', 'email', 'max:150'],
            'telefono'  => ['nullable', 'string', 'max:20'],
            'estado'    => ['sometimes', Rule::in([
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
            'tipo'     => ['required', 'string', 'max:100'],
            'archivo'  => ['required', 'file',
                'mimes:pdf,jpg,jpeg,png,doc,docx',
                'max:10240',
            ],
        ]);

        $archivo = $request->file('archivo');
        $ruta    = $archivo->store(
            "seleccion/postulantes/{$postulanteId}", 'public'
        );

        $documento = DocumentoPostulante::create([
            'postulante_id'  => $postulanteId,
            'tipo'           => $request->input('tipo'),
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'ruta'           => $ruta,
            'extension'      => $archivo->getClientOriginalExtension(),
            'tamano_bytes'   => $archivo->getSize(),
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
