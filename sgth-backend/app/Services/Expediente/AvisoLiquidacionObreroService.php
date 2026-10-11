<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Mail\Expediente\LiquidacionObreroMail;
use App\Models\Expediente\MovimientoPersonal;
use App\Services\Disciplinario\AvisoFinancieroSancionService;
use App\Services\Estructura\FirmanteOrganigramaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lleva a Financiero la liquidación referencial de la terminación de un obrero
 * (diseño de Acciones de Personal, 9.15; fase 4.5), como la multa: al
 * registrarse sale por correo al jefe de la unidad financiera con la hoja de
 * registro interno, y si se anula después, el aviso contrario.
 *
 * Solo las causales con algo que pagar según el Código: el desahucio, el
 * acuerdo de las partes y el despido intempestivo. Y el reintegro por despido
 * ineficaz, con sus remuneraciones pendientes (fase 4.5b). Si no hay jefe o el correo
 * falla, la transición sigue: la pantalla avisa que hay que enviarlo a mano.
 */
class AvisoLiquidacionObreroService
{
    public function __construct(
        private readonly FirmanteOrganigramaService $organigrama,
        private readonly AccionPersonalPdfService $pdf,
        private readonly TerminacionObreroService $terminaciones,
    ) {
    }

    /**
     * @return AvisoFinancieroSancionService::ENVIADO|AvisoFinancieroSancionService::SIN_DESTINATARIO|AvisoFinancieroSancionService::FALLO_ENVIO|null
     */
    public function trasTransicion(MovimientoPersonal $movimiento, EstadoAccionPersonal $origen): ?string
    {
        $registrada = $movimiento->estado === EstadoAccionPersonal::REGISTRADA;
        $anuladaTrasEnviarse = $movimiento->estado === EstadoAccionPersonal::ANULADA
            && in_array($origen, [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA], true);

        if (! $registrada && ! $anuladaTrasEnviarse) {
            return null;
        }

        $liquidacion = $this->terminaciones->liquidacion($movimiento);

        if ($liquidacion === null) {
            return null;
        }

        $direccion = $this->direccionDelJefeFinanciero();

        if (! $direccion) {
            Log::warning('Liquidación de obrero sin destinatario: la unidad financiera no tiene jefe con usuario', [
                'movimiento' => $movimiento->id,
            ]);

            return AvisoFinancieroSancionService::SIN_DESTINATARIO;
        }

        try {
            $movimiento->loadMissing('servidor');
            $documento = $this->pdf->generarContent($movimiento->id);

            Mail::to($direccion)->send(new LiquidacionObreroMail(
                $movimiento,
                $liquidacion,
                $anuladaTrasEnviarse,
                $documento['content'],
                $documento['filename'],
            ));
        } catch (\Throwable $e) {
            Log::error('No salió el correo de la liquidación del obrero', [
                'movimiento' => $movimiento->id,
                'motivo'     => $e->getMessage(),
            ]);

            return AvisoFinancieroSancionService::FALLO_ENVIO;
        }

        return AvisoFinancieroSancionService::ENVIADO;
    }

    private function direccionDelJefeFinanciero(): ?string
    {
        $unidad = $this->organigrama->unidadAnclada('es_unidad_financiera');

        if (! $unidad) {
            return null;
        }

        $jefe = $this->organigrama->jefeDeUnidadEn($unidad, now()->toDateString())['servidor'];

        return $jefe?->loadMissing('usuario')->usuario?->email;
    }
}
