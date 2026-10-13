<?php

namespace App\Services\InventarioTi\Prestamos;

use App\Enums\Permiso;
use App\Mail\InventarioTi\PrestamoEquipoMail;
use App\Models\InventarioTi\PrestamoEquipo;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Los correos del préstamo de equipos (fase 2.4; D17 para la entrega y la
 * devolución).
 *
 * La dirección es la de siempre: la institucional de su cuenta y, si no
 * tiene, la personal. Un correo que no sale no deshace el paso: el préstamo
 * ya está registrado y se ve en el Portal.
 */
final class AvisoPrestamoService
{
    /** Al servidor del préstamo. Devuelve si salió. */
    public function alServidor(PrestamoEquipo $prestamo, string $aviso): bool
    {
        $prestamo->loadMissing('servidor.usuario', 'items.bien.tipo', 'items.bien.marca');
        $s = $prestamo->servidor;
        $direccion = $s?->usuario?->email ?: $s?->correo_personal;

        return $direccion ? $this->enviar($direccion, $prestamo, $aviso) : false;
    }

    /** A quienes gestionan préstamos en TIC: el préstamo vencido. Devuelve a cuántos salió. */
    public function aTic(PrestamoEquipo $prestamo): int
    {
        $prestamo->loadMissing('servidor', 'items.bien.tipo', 'items.bien.marca');

        return User::permission(Permiso::GESTIONAR_PRESTAMOS->value)
            ->where('activo', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->unique()
            ->filter(fn (string $correo) => $this->enviar($correo, $prestamo, 'vencido_tic'))
            ->count();
    }

    private function enviar(string $direccion, PrestamoEquipo $prestamo, string $aviso): bool
    {
        try {
            Mail::to($direccion)->send(new PrestamoEquipoMail($prestamo, $aviso));

            return true;
        } catch (\Throwable $e) {
            Log::warning('No salió el aviso del préstamo de equipos', [
                'prestamo' => $prestamo->folio, 'aviso' => $aviso, 'motivo' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
