<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\CertificadoMedico;
use App\Services\Dispensario\CertificadoMedicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificadoMedicoController extends Controller
{
    public function __construct(
        private CertificadoMedicoService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = CertificadoMedico::with([
            'consultaMedica.historiaClinica.servidor',
            'consultaMedica.historiaClinica.cargaFamiliar',
            'emisor',
            'diagnosticoCie10',
            'permisoServidor',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('servidor_id')) {
            $query->whereHas(
                'consultaMedica.historiaClinica',
                fn($q) => $q->where(
                    'servidor_id', $request->servidor_id
                )
            );
        }

        if ($request->filled('consulta_medica_id')) {
            $query->where(
                'consulta_medica_id',
                $request->integer('consulta_medica_id')
            );
        }

        $certificados = $query->paginate(
            $request->integer('per_page', 20)
        );

        return ApiResponse::ok(
            $certificados, 'Listado de certificados médicos.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'consulta_medica_id'   => ['required', 'exists:consultas_medicas,id'],
            'dias_reposo'          => ['required', 'integer', 'min:1', 'max:3'],
            'fecha_inicio'         => ['nullable', 'date'],
            'fecha_fin'            => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'diagnostico_cie10_id' => ['nullable', 'exists:diagnosticos_cie10,id'],
            'observaciones'        => ['nullable', 'string', 'max:1000'],
        ]);

        $certificado = $this->service->emitir(
            $datos, $request->user()->id
        );

        return ApiResponse::created(
            $certificado,
            'Certificado médico emitido correctamente.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $certificado = CertificadoMedico::with([
            'consultaMedica.historiaClinica.servidor',
            'consultaMedica.historiaClinica.cargaFamiliar',
            'emisor',
            'diagnosticoCie10',
            'permisoServidor',
        ])->findOrFail($id);

        return ApiResponse::ok($certificado);
    }
}
