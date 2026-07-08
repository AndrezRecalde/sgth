<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Dispensario\FemoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FichaSaludOcupacionalController extends Controller
{
    public function __construct(
        private readonly FemoService $femoService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $fichas = $this->femoService->listar(
            $request->only([
                'servidor_id', 'tipo_ficha', 'aptitud',
                'fecha_desde', 'fecha_hasta', 'per_page',
            ])
        );

        return ApiResponse::ok($fichas);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'ficha.servidor_id'         => ['required', 'integer', 'exists:servidores,id'],
            'ficha.fecha_evaluacion'     => ['required', 'date'],
            'ficha.tipo_ficha'           => ['required', 'in:ingreso,periodica,reintegro,retiro,especial'],
            'ficha.aptitud'              => ['required', 'in:apto,apto_con_restricciones,en_observacion,no_apto'],
            'ficha.puesto_trabajo'       => ['nullable', 'string', 'max:200'],
            'ficha.restricciones'        => ['nullable', 'string'],
            'ficha.observaciones'        => ['nullable', 'string'],
            'ficha.recomendaciones'      => ['nullable', 'string'],
            'ficha.enfermedad_actual'    => ['nullable', 'string'],
            'ficha.condicion_relacionada_trabajo' => ['nullable', 'boolean'],
            'constantes_vitales'         => ['nullable', 'array'],
            'antecedentes'               => ['nullable', 'array'],
            'antecedentes.*.tipo'        => ['required', 'string'],
            'antecedentes.*.descripcion' => ['required', 'string'],
            'factores_riesgo'            => ['nullable', 'array'],
            'factores_riesgo.*.categoria'=> ['required', 'string'],
            'factores_riesgo.*.factor'   => ['required', 'string'],
            'diagnosticos'               => ['nullable', 'array', 'max:6'],
            'diagnosticos.*.diagnostico_cie10_id' => ['required', 'integer', 'exists:diagnosticos_cie10,id'],
            'diagnosticos.*.tipo'        => ['required', 'in:presuntivo,definitivo'],
            'diagnosticos.*.orden'       => ['required', 'integer', 'min:1', 'max:6'],
            'examenes'                   => ['nullable', 'array'],
            'examenes.*.nombre_examen'   => ['required', 'string'],
            'examenes.*.tipo'            => ['required', 'in:laboratorio,imagen,otro'],
            'empleos_anteriores'         => ['nullable', 'array'],
            'empleos_anteriores.*.centro_trabajo' => ['required', 'string'],
        ]);

        $ficha = $this->femoService->registrar(
            $request->all(),
            $request->user()->id
        );

        return ApiResponse::created($ficha, 'Ficha FEMO registrada correctamente.');
    }

    public function show(int $id): JsonResponse
    {
        $ficha = $this->femoService->obtener($id);
        return ApiResponse::ok($ficha);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ficha = $this->femoService->actualizar(
            $id,
            $request->all(),
            $request->user()->id
        );

        return ApiResponse::ok($ficha, 'Ficha FEMO actualizada correctamente.');
    }
}