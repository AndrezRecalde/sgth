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
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class ResultadoMedicoController extends Controller
{
    /**
     * Dónde viven los archivos: el disco privado.
     *
     * Estaban en el público, servidos en `/storage/resultados-medicos/…` a
     * cualquiera que tuviera la dirección, sin sesión: un laboratorio o una
     * radiografía son datos de salud. Ahora solo salen por `archivo()`, con la
     * sesión y el rol del Dispensario, como el respaldo de una adquisición.
     */
    private const DISCO = 'local';

    /**
     * Los resultados de UN paciente, y de una consulta suya si se pide.
     *
     * El paciente era un filtro opcional y el listado no paginaba: sin él
     * salían de una vez los resultados de todos los pacientes del
     * Dispensario. La pantalla siempre lo mandaba, pero el API no lo exigía.
     * Ahora es obligatorio; acotado a un paciente, el listado es corto y no
     * hace falta paginarlo.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'historia_clinica_id' => ['required', 'integer'],
            'consulta_medica_id'  => ['nullable', 'integer'],
        ], [
            'historia_clinica_id.required' => 'Indique de qué paciente son los resultados.',
        ]);

        $query = ResultadoMedico::with([
            'subidoPor:id,usuario_ti,email,servidor_id',
            'subidoPor.servidor:id,nombre,apellido',
        ])
            ->where('historia_clinica_id', $request->integer('historia_clinica_id'))
            ->orderBy('fecha_resultado', 'desc')
            // Varios resultados del mismo día: sin desempate, el orden cambia
            // entre una carga y otra.
            ->orderBy('id', 'desc');

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
            // La consulta, de ese mismo paciente. Solo se comprobaba que
            // existiera: el resultado de un paciente quedaba colgado de la
            // consulta de otro, y se enseñaba en el panel de este.
            'consulta_medica_id'  => ['nullable', 'integer',
                Rule::exists('consultas_medicas', 'id')
                    ->where('historia_clinica_id', $request->integer('historia_clinica_id'))
                    ->whereNull('deleted_at'),
            ],
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
        ], [
            'consulta_medica_id.exists' => 'La consulta indicada no es de este paciente.',
        ]);

        $ruta = $request->file('archivo')->store(
            'resultados-medicos', self::DISCO
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
            $resultado->load([
                'subidoPor:id,usuario_ti,email,servidor_id',
                'subidoPor.servidor:id,nombre,apellido',
            ]),
            'Resultado médico registrado correctamente.'
        );
    }

    /** El archivo, para verlo. Solo con la sesión y el rol de la ruta. */
    public function archivo(int $id): SymfonyResponse
    {
        $resultado = ResultadoMedico::findOrFail($id);
        $disco     = Storage::disk(self::DISCO);

        if (! $disco->exists($resultado->archivo)) {
            return ApiResponse::error(
                'El archivo de este resultado ya no está disponible.', null, 404
            );
        }

        $extension = pathinfo($resultado->archivo, PATHINFO_EXTENSION);

        return response($disco->get($resultado->archivo), 200, [
            'Content-Type'        => $disco->mimeType($resultado->archivo),
            'Content-Disposition' => 'inline; filename="resultado-'
                . $resultado->id . '.' . $extension . '"',
        ]);
    }

    /**
     * Retira un resultado de la consulta.
     *
     * Lo retira quien lo subió —se equivocó de archivo o de paciente— o la
     * administración del Dispensario; antes podía cualquier profesional, sobre
     * el resultado de cualquiera. Y ya no se borra el archivo: el registro se
     * retira en blando y el archivo se queda en el disco privado, porque un
     * documento de la historia clínica no desaparece sin rastro. Antes la fila
     * quedaba borrada en blando apuntando a un archivo que ya no existía.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $resultado = ResultadoMedico::findOrFail($id);
        $usuario   = $request->user();

        if (
            $resultado->subido_por !== $usuario->id
            && ! $usuario->hasRole('admin-dispensario')
        ) {
            return ApiResponse::error(
                'Solo quien subió el resultado o la administración del ' .
                'Dispensario pueden retirarlo.',
                null,
                403
            );
        }

        $resultado->delete();

        return ApiResponse::ok([], 'Resultado retirado de la consulta.');
    }
}
