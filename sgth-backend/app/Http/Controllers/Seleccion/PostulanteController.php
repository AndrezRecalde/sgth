<?php

namespace App\Http\Controllers\Seleccion;

use App\Enums\EstadoConvocatoria;
use App\Enums\EstadoPostulante;
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
use Illuminate\Validation\ValidationException;

final class PostulanteController extends Controller
{
    public function index(int $convocatoriaId): JsonResponse
    {
        Convocatoria::findOrFail($convocatoriaId);

        // Con su solicitud médica (2026-10-04): el ranking del concurso formal
        // ofrece «Confirmar incorporación» cuando hay dictamen de aptitud, igual
        // que el cajón del express.
        // Y su inducción (2026-10-05), para el perfil del incorporado.
        $postulantes = Postulante::with(['evaluacion', 'documentos', 'solicitudCertificacion', 'onboarding'])
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

        // Solo publicada: `en_proceso` no existe como estado. Un contenedor
        // express está siempre publicado.
        if ($convocatoria->estado !== EstadoConvocatoria::PUBLICADA) {
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
            // Diez dígitos, como en el Expediente (StoreServidorBasicoRequest):
            // la columna es varchar(10), así que `max:20` dejaba pasar una
            // cédula de 11 caracteres hasta la base y daba un 500. Y al
            // incorporar, este valor pasa a `servidores`.
            'cedula' => ['required', 'string', 'regex:/^\d{10}$/'],
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
        ], [
            'cedula.regex' => 'La cédula debe tener 10 dígitos numéricos.',
        ]);

        $datos['fecha_inscripcion'] = $datos['fecha_inscripcion'] ?? now()->toDateString();

        // En un contenedor permanente la unicidad es por año: la misma persona
        // puede ser contratada en 2026 y otra vez en 2027 bajo la misma
        // modalidad. En un concurso formal sigue siendo una sola inscripción.
        $this->assertCedulaLibre($convocatoria, $datos['cedula'], $datos['fecha_inscripcion']);

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

    /**
     * Corregir los datos de un candidato desde su perfil (2026-10-05): el
     * endpoint existía pero ninguna pantalla lo usaba, y solo aceptaba cuatro
     * campos. No se corrige a quien ya fue incorporado —sus datos viven ya en
     * el expediente— ni en un concurso cerrado; y la cédula, solo mientras no
     * se haya enviado al Dispensario, que trabaja con ella.
     */
    public function update(
        Request $request,
        int $convocatoriaId,
        int $postulanteId
    ): JsonResponse {
        $postulante = Postulante::with('convocatoria')
            ->where('convocatoria_id', $convocatoriaId)
            ->findOrFail($postulanteId);

        if ($postulante->estado === EstadoPostulante::INCORPORADO || $postulante->convocatoria->estado->esTerminal()) {
            throw new ReglaNegocioException(
                'Este candidato ya fue incorporado o el concurso está cerrado: sus datos no se corrigen aquí.'
            );
        }

        $datos = $request->validate([
            'cedula' => ['sometimes', 'string', 'regex:/^\d{10}$/'],
            'nombres' => ['sometimes', 'string', 'max:150'],
            'segundo_nombre' => ['nullable', 'string', 'max:150'],
            'apellidos' => ['sometimes', 'string', 'max:150'],
            'segundo_apellido' => ['nullable', 'string', 'max:150'],
            'correo' => ['sometimes', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'genero' => ['sometimes', 'string', 'in:masculino,femenino,otro'],
            'estado_civil' => ['nullable', 'string', 'in:soltero,casado,union_libre,divorciado,viudo'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'tipo_sangre' => ['nullable', 'string', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            // Sin `estado` (2026-10-05): fijarlo a mano saltaba la calificación,
            // el dictamen y el ranking. El estado lo mueven sus acciones.
        ], [
            'cedula.regex' => 'La cédula debe tener 10 dígitos numéricos.',
        ]);

        if (isset($datos['cedula']) && $datos['cedula'] !== $postulante->cedula) {
            if (! $postulante->estado->admiteCalificacion()) {
                throw ValidationException::withMessages([
                    'cedula' => 'El candidato ya fue enviado al Dispensario: la cédula ya no se cambia.',
                ]);
            }
            $this->assertCedulaLibre($postulante->convocatoria, $datos['cedula'], $postulante->fecha_inscripcion?->toDateString(), $postulante->id);
            $datos['servidor_id'] = Servidor::where('cedula', $datos['cedula'])->value('id');
        }

        $postulante->update([
            ...$datos,
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::ok($postulante->fresh(), 'Datos del candidato actualizados.');
    }

    /**
     * Una sola inscripción por cédula en un concurso formal; en un contenedor
     * express, una por año. El error va al campo de la cédula.
     */
    private function assertCedulaLibre(Convocatoria $convocatoria, string $cedula, ?string $fecha, ?int $excepto = null): void
    {
        $esContenedor = (bool) $convocatoria->es_contenedor_permanente;

        $existe = Postulante::where('convocatoria_id', $convocatoria->id)
            ->where('cedula', $cedula)
            ->when($excepto, fn ($q) => $q->where('id', '!=', $excepto))
            ->when($esContenedor && $fecha, fn ($q) => $q->whereYear('fecha_inscripcion', (int) substr($fecha, 0, 4)))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'cedula' => $esContenedor
                    ? 'Ya existe un aspirante con esa cédula en esta modalidad para ese año.'
                    : 'Ya existe un postulante con esa cédula en esta convocatoria.',
            ]);
        }
    }

    public function destroy(
        int $convocatoriaId,
        int $postulanteId
    ): JsonResponse {
        $postulante = Postulante::with('convocatoria')
            ->where('convocatoria_id', $convocatoriaId)
            ->findOrFail($postulanteId);

        // Borrar a quien ya fue enviado al Dispensario o incorporado dejaba su
        // solicitud médica y su vacante colgando; y un concurso cerrado es un
        // registro de lo que pasó (2026-10-05).
        if (! $postulante->estado->admiteCalificacion() || $postulante->convocatoria->estado->esTerminal()) {
            throw new ReglaNegocioException(
                'Este candidato ya avanzó en el proceso o el concurso está cerrado: no se puede eliminar.'
            );
        }

        // Es un borrado lógico: el postulante y sus documentos siguen en la
        // base, así que sus archivos se quedan. Antes se borraban aquí y las
        // filas de `documentos_postulante` apuntaban a archivos que ya no
        // existían.
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

        // Disco privado (2026-10-04). Iba al `public`, enlazado en
        // public/storage: la cédula y la hoja de vida de cada postulante se
        // abrían sin iniciar sesión con solo conocer la ruta, que además salía
        // en el JSON. Ahora se bajan por descargarDocumento(), que autoriza.
        $archivo = $request->file('archivo');
        $ruta = $archivo->store(
            "seleccion/postulantes/{$postulanteId}", 'local'
        );

        $documento = DocumentoPostulante::create([
            'postulante_id' => $postulanteId,
            'tipo' => $request->input('tipo'),
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'ruta' => $ruta,
            // La del contenido, no la que trae el nombre: la columna es
            // varchar(10) y una extensión inventada daba un 500.
            'extension' => $archivo->extension(),
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

        Storage::disk('local')->delete($documento->ruta);
        $documento->delete();

        return ApiResponse::ok([], 'Documento eliminado.');
    }

    /** Descarga un documento del postulante: vive en el disco privado. */
    public function descargarDocumento(
        int $convocatoriaId,
        int $postulanteId,
        int $documentoId
    ): mixed {
        Postulante::where('convocatoria_id', $convocatoriaId)->findOrFail($postulanteId);

        $documento = DocumentoPostulante::where('postulante_id', $postulanteId)
            ->findOrFail($documentoId);

        if (! Storage::disk('local')->exists($documento->ruta)) {
            return ApiResponse::error('No se encontró el archivo del documento.', null, 404);
        }

        return Storage::disk('local')->download($documento->ruta, $documento->nombre_archivo);
    }
}
