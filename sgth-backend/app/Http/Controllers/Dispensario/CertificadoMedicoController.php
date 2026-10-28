<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\CertificadoMedico;
use App\Models\Dispensario\ConsultaMedica;
use App\Services\Dispensario\CertificadoMedicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CertificadoMedicoController extends Controller
{
    public function __construct(
        private CertificadoMedicoService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Con el anulador: la tarjeta de la consulta pinta quién lo anuló, y el
        // listado no lo traía.
        $query = CertificadoMedico::with(CertificadoMedicoService::RELACIONES)
            // created_at es timestamp(0): sin desempate por id, dos páginas
            // del mismo resultado pueden solaparse.
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

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

        // Con techo y suelo, como el listado de consultas: `limit()` ignora los
        // negativos, así que `?per_page=-1` devolvía todos los certificados, con
        // el diagnóstico de cada paciente, en una sola respuesta.
        $certificados = $query->paginate(
            min(max($request->integer('per_page', 20), 1), 100)
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

        // Como la receta: el certificado sale con el nombre y el diagnóstico de
        // quien atendió, así que solo lo emite él. Antes cualquier médico u
        // odontólogo podía emitirlo sobre la consulta de otro
        // (docs/plan-roles-y-accesos.md, PR 4).
        $medicoDeLaConsulta = ConsultaMedica::whereKey($datos['consulta_medica_id'])->value('medico_id');

        if ((int) $medicoDeLaConsulta !== $request->user()->id) {
            return ApiResponse::error(
                'Solo quien atendió la consulta puede emitir el certificado de reposo.',
                null,
                403
            );
        }

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
        $certificado = CertificadoMedico::with(CertificadoMedicoService::RELACIONES)
            ->findOrFail($id);

        return ApiResponse::ok($certificado);
    }

    /**
     * Anula el certificado y, si ya se registró en Sirha7, lo retira de allí.
     *
     * Lo puede hacer quien lo emitió o la administración del dispensario, la
     * misma regla que ya rige para anular una receta: el mostrador tiene que
     * poder corregir cuando el médico ya no está.
     */
    public function anular(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'motivo_anulacion' => ['required', 'string', 'max:255'],
        ]);

        $certificado = CertificadoMedico::findOrFail($id);

        $esAdministracion = $request->user()->hasRole('admin-dispensario');
        $loEmitio = $certificado->emitido_por === $request->user()->id;

        if (! $esAdministracion && ! $loEmitio) {
            return ApiResponse::error(
                'Solo quien emitió el certificado o la administración del ' .
                'dispensario pueden anularlo.',
                null,
                403
            );
        }

        $certificado = $this->service->anular(
            $id,
            $request->string('motivo_anulacion')->value(),
            $request->user()->id
        );

        return ApiResponse::ok(
            $certificado,
            'Certificado anulado correctamente.'
        );
    }

    /** El PDF, para imprimirlo o entregárselo al paciente. */
    public function pdf(int $id): Response
    {
        $resultado = $this->service->generarPdf($id);

        return response($resultado['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'
                . $resultado['filename'] . '"',
        ]);
    }
}
