<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreResultadoMedicoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\ResultadoMedico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

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
     * Quién lo subió y quién lo revisó; si vino de una orden, cuál, quién la
     * pidió y qué exámenes cubre. Y el paciente, para darle un turno de
     * control desde un resultado alterado.
     */
    public const RELACIONES = [
        'historiaClinica:id,servidor_id,carga_familiar_id',
        'subidoPor:id,usuario_ti,email,servidor_id',
        'subidoPor.servidor:id,nombre,apellido',
        'revisor:id,usuario_ti,email,servidor_id',
        'revisor.servidor:id,nombre,apellido',
        'ordenExamen:id,folio,estado,solicitado_por',
        'itemsOrden:id,orden_examen_id,catalogo_examen_id',
        'itemsOrden.examen:id,nombre',
    ];

    public function __construct(
        private readonly OrdenExamenServiceInterface $ordenes
    ) {}

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
            ...self::RELACIONES,
            // Para decir de qué consulta es cada uno cuando se listan todos
            // los del paciente.
            'consultaMedica:id,fecha_consulta',
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

    public function store(StoreResultadoMedicoRequest $request): JsonResponse
    {
        $ruta = $request->file('archivo')->store(
            'resultados-medicos', self::DISCO
        );

        try {
            $resultado = DB::transaction(function () use ($request, $ruta) {
                $resultado = ResultadoMedico::create([
                    'historia_clinica_id' => $request->integer('historia_clinica_id'),
                    'consulta_medica_id'  => $request->integer('consulta_medica_id') ?: null,
                    'orden_examen_id'     => $request->integer('orden_examen_id') ?: null,
                    'subido_por'          => $request->user()->id,
                    'tipo'                => $request->input('tipo'),
                    'descripcion'         => $request->input('descripcion'),
                    'fecha_resultado'     => $request->input('fecha_resultado'),
                    'archivo'             => $ruta,
                ]);

                if ($resultado->orden_examen_id !== null) {
                    $this->ordenes->adjuntarResultado(
                        $resultado,
                        array_map('intval', (array) $request->input('items'))
                    );
                }

                return $resultado;
            });
        } catch (Throwable $e) {
            // Sin registro, el archivo no lo encontraría nadie.
            Storage::disk(self::DISCO)->delete($ruta);

            throw $e;
        }

        return ApiResponse::created(
            $resultado->load(self::RELACIONES),
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

        // Lo que cubría de su orden vuelve a pendiente si nada más lo cubre
        // (regla 4 del diseño).
        DB::transaction(function () use ($resultado) {
            $resultado->delete();
            $this->ordenes->liberarResultado($resultado);
        });

        return ApiResponse::ok([], 'Resultado retirado de la consulta.');
    }
}
