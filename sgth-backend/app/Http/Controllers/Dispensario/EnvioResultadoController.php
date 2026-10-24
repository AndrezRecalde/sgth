<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\ValidarEnvioResultadoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\EnvioResultado;
use App\Services\Dispensario\EnvioResultadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lo que los servidores envían desde el Portal, del lado del Dispensario (fase
 * 6 de docs/diseno-examenes-medicos.md): verlo y validarlo o rechazarlo. Lo
 * atiende un médico o un odontólogo (D2); Enfermería lo ve en la bandeja.
 */
final class EnvioResultadoController extends Controller
{
    /** Lo que acompaña a un envío en la bandeja y en la respuesta de atenderlo. */
    public const RELACIONES = [
        'ordenExamen:id,folio,estado,historia_clinica_id,consulta_medica_id,solicitado_por,fecha_solicitud',
        'ordenExamen.items:id,orden_examen_id,catalogo_examen_id,nota,estado',
        'ordenExamen.items.examen:id,nombre,area',
        'ordenExamen.historiaClinica:id,servidor_id,carga_familiar_id',
        'ordenExamen.historiaClinica.servidor:id,nombre,apellido,cedula',
        'ordenExamen.solicitante:id,usuario_ti,email,servidor_id',
        'ordenExamen.solicitante.servidor:id,nombre,apellido',
    ];

    public function __construct(private readonly EnvioResultadoService $envios) {}

    /** El archivo, para mirarlo antes de validarlo. */
    public function archivo(int $envio): StreamedResponse
    {
        return $this->envios->descargar(EnvioResultado::findOrFail($envio));
    }

    public function validar(ValidarEnvioResultadoRequest $request, int $envio): JsonResponse
    {
        $validado = $this->envios->validar($envio, $request->validated(), $request->user());

        return ApiResponse::ok(
            $validado->refresh()->load(self::RELACIONES),
            'Resultado validado: ya está en la historia clínica, por revisar.'
        );
    }

    public function rechazar(Request $request, int $envio): JsonResponse
    {
        $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:500'],
        ], [
            'motivo_rechazo.required' => 'Indique por qué no se acepta: el servidor lo verá en el Portal.',
        ]);

        $rechazado = $this->envios->rechazar(
            $envio,
            trim($request->string('motivo_rechazo')->value()),
            $request->user(),
        );

        return ApiResponse::ok(
            $rechazado->refresh()->load(self::RELACIONES),
            'Envío rechazado. El servidor verá el motivo en el Portal.'
        );
    }
}
