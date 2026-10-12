<?php

namespace App\Services\Expediente;

use App\Mail\Expediente\AccionPersonalNotificadaMail;
use App\Models\Expediente\MovimientoPersonal;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * El correo de la notificación por el portal (diseño, 6.4 y 9.13; TH 26; fase
 * 6.3a): el documento adjunto y el enlace a «Mis documentos laborales».
 *
 * La dirección sigue la regla de la UATH del certificado laboral: la
 * institucional de su cuenta y, si no tiene, la personal. Si no hay ninguna o
 * el correo no sale, la notificación queda igual —el documento ya está en el
 * portal— y la acción dice por qué, para que Talento Humano se lo entregue.
 */
class AvisoNotificacionService
{
    public function __construct(
        private readonly AccionPersonalPdfService $pdf,
        private readonly CertificadoLaboralService $certificados,
    ) {}

    /** @return 'institucional'|'personal'|'sin_direccion'|'fallo' */
    public function enviar(MovimientoPersonal $movimiento): string
    {
        $movimiento->loadMissing('servidor.usuario');
        $destino = $this->certificados->destinatario($movimiento->servidor);

        if (! $destino['direccion']) {
            return $this->anotar($movimiento, 'sin_direccion', false);
        }

        // Un correo que no sale no deshace la notificación.
        try {
            $documento = $this->pdf->generarContent($movimiento->id);

            Mail::to($destino['direccion'])->send(new AccionPersonalNotificadaMail(
                $movimiento,
                $documento['content'],
                $documento['filename'],
            ));
        } catch (\Throwable $e) {
            Log::error('No salió el correo de la notificación de la acción de personal', [
                'movimiento' => $movimiento->id,
                'motivo'     => $e->getMessage(),
            ]);

            return $this->anotar($movimiento, 'fallo', false);
        }

        return $this->anotar($movimiento, $destino['origen'], true);
    }

    private function anotar(MovimientoPersonal $movimiento, string $destino, bool $salio): string
    {
        $movimiento->forceFill([
            'correo_destino'    => $destino,
            'correo_enviado_en' => $salio ? now() : null,
        ])->save();

        return $destino;
    }
}
