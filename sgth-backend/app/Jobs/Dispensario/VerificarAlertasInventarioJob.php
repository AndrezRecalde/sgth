<?php
namespace App\Jobs\Dispensario;

use App\Contracts\Dispensario\InventarioMedicinasServiceInterface;
use App\Mail\Dispensario\AlertasInventarioMail;
use App\Models\User;
use App\Services\Dispensario\InventarioMedicinasService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Aviso diario del estado del inventario. Lo agenda `routes/console.php` a las
 * 06:00.
 *
 * Antes solo escribía en el log —el propio código dejó anotado que ahí debía ir
 * un envío—, así que en la práctica nadie se enteraba de que algo estaba bajo
 * mínimo salvo abriendo la pantalla y mirando la insignia.
 */
class VerificarAlertasInventarioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(InventarioMedicinasServiceInterface $inventario): void
    {
        $resumen = $inventario->resumenAlertas();

        $hayAlgo = $resumen['bajo_minimo']->isNotEmpty()
            || $resumen['por_caducar']->isNotEmpty()
            || $resumen['caducadas']->isNotEmpty();

        if (! $hayAlgo) {
            return;
        }

        // El log se queda: sirve de rastro cuando el correo no sale, y hasta
        // ahora era lo único que existía.
        Log::warning('Alertas de inventario del dispensario', [
            'bajo_minimo' => $resumen['bajo_minimo']->pluck('nombre'),
            'caducadas'   => $resumen['caducadas']->pluck('nombre'),
            'por_caducar' => $resumen['por_caducar']->pluck('nombre'),
        ]);

        $destinatarios = $this->destinatarios();

        if ($destinatarios->isEmpty()) {
            Log::warning(
                'Hay alertas de inventario pero ningún admin-dispensario con ' .
                'correo a quien avisar.'
            );

            return;
        }

        Mail::to($destinatarios->all())->queue(new AlertasInventarioMail(
            bajoMinimo: $resumen['bajo_minimo'],
            porCaducar: $resumen['por_caducar'],
            caducadas:  $resumen['caducadas'],
            diasAviso:  InventarioMedicinasService::DIAS_AVISO_CADUCIDAD,
        ));
    }

    /**
     * Quien administra la farmacia. Se excluyen los usuarios inactivos y los
     * que no tienen correo: encolar un envío sin destinatario solo produce un
     * fallo de cola sin nada que lo explique.
     */
    private function destinatarios()
    {
        return User::role('admin-dispensario')
            ->where('activo', true)
            ->whereNotNull('email')
            ->pluck('email');
    }
}
