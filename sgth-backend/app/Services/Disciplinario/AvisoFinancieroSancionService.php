<?php

namespace App\Services\Disciplinario;

use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Mail\Disciplinario\SancionParaNominaMail;
use App\Models\Disciplinario\SancionDisciplinaria;
use App\Models\Expediente\MovimientoPersonal;
use App\Services\Estructura\FirmanteOrganigramaService;
use App\Services\Expediente\AccionPersonalPdfService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lleva a Financiero la acción de personal de una multa o una suspensión
 * (decisión de TH, 2026-10-04).
 *
 * - Cuando Talento Humano la REGISTRA, sale por correo al jefe de la unidad
 *   marcada como financiera (o a quien lo subrogue ese día), con el PDF y el
 *   descuento referencial. Él dispone a quién corresponde aplicarlo.
 * - Si una acción ya enviada se ANULA, sale el aviso contrario, para que el
 *   descuento se revierta si llegó a aplicarse.
 *
 * El correo va a la cuenta institucional del jefe —la de su usuario—, como el
 * resto de avisos internos. Si no hay jefe, no tiene usuario o el servidor de
 * correo falla, la transición sigue adelante: el acto ya está registrado, y lo
 * que se le devuelve a la pantalla es que hay que enviarlo a mano.
 */
class AvisoFinancieroSancionService
{
    public const ENVIADO          = 'enviado';
    public const SIN_DESTINATARIO = 'sin_destinatario';
    public const FALLO_ENVIO      = 'fallo_envio';

    public function __construct(
        private readonly FirmanteOrganigramaService $organigrama,
        private readonly AccionPersonalPdfService $pdf,
    ) {
    }

    /**
     * @return self::ENVIADO|self::SIN_DESTINATARIO|self::FALLO_ENVIO|null  null si no tocaba avisar
     */
    public function trasTransicion(MovimientoPersonal $movimiento, EstadoAccionPersonal $origen): ?string
    {
        if ($movimiento->subtipo_movimiento !== SubtipoMovimientoPersonal::SANCION_DISCIPLINARIA) {
            return null;
        }

        $registrada = $movimiento->estado === EstadoAccionPersonal::REGISTRADA;
        $anuladaTrasEnviarse = $movimiento->estado === EstadoAccionPersonal::ANULADA
            && in_array($origen, [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA], true);

        if (! $registrada && ! $anuladaTrasEnviarse) {
            return null;
        }

        // Solo las que nacieron de una multa o una suspensión: una acción de
        // sanción hecha a mano no dice cuánto descontar.
        $sancion = SancionDisciplinaria::where('movimiento_personal_id', $movimiento->id)->first();
        if (! $sancion?->afectaRemuneracion()) {
            return null;
        }

        $direccion = $this->direccionDelJefeFinanciero();
        if (! $direccion) {
            Log::warning('Sanción para nómina sin destinatario: la unidad financiera no tiene jefe con usuario', [
                'movimiento' => $movimiento->id,
            ]);

            return self::SIN_DESTINATARIO;
        }

        try {
            $movimiento->loadMissing('servidor');
            $documento = $this->pdf->generarContent($movimiento->id);

            Mail::to($direccion)->send(new SancionParaNominaMail(
                $movimiento,
                $sancion->descuentoReferencial(
                    $movimiento->remuneracion_origen !== null ? (float) $movimiento->remuneracion_origen : null
                ),
                $anuladaTrasEnviarse,
                $documento['content'],
                $documento['filename'],
            ));
        } catch (\Throwable $e) {
            Log::error('No salió el correo de la sanción para nómina', [
                'movimiento' => $movimiento->id,
                'motivo'     => $e->getMessage(),
            ]);

            return self::FALLO_ENVIO;
        }

        return self::ENVIADO;
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
