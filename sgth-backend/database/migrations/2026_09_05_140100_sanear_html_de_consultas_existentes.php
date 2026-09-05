<?php

use App\Models\Dispensario\ConsultaMedica;
use App\Support\HtmlClinico;
use Illuminate\Database\Migrations\Migration;

/**
 * Pasa por el saneador las consultas que ya estaban guardadas.
 *
 * Limpiar solo lo que entre a partir de ahora dejaría el agujero abierto para
 * lo que ya está: el HTML almacenado se pinta igual, se guardara ayer o hoy.
 *
 * Va por Eloquent y no por SQL porque estos campos están cifrados: hay que
 * descifrar, limpiar y volver a cifrar. Se recorre en trozos por si algún día
 * son muchas.
 *
 * No tiene `down()` con sentido: lo que se retiró era código que no debía estar
 * en una nota clínica, y devolverlo sería reponer el problema.
 */
return new class extends Migration
{
    public function up(): void
    {
        ConsultaMedica::withTrashed()->chunkById(200, function ($consultas) {
            foreach ($consultas as $consulta) {
                $limpios = [];

                foreach (ConsultaMedica::CAMPOS_HTML as $campo) {
                    $original = $consulta->{$campo};
                    $limpio   = HtmlClinico::limpiar($original);

                    if ($limpio !== $original) {
                        $limpios[$campo] = $limpio;
                    }
                }

                if ($limpios) {
                    // Sin tocar `updated_at`: esto es higiene del sistema, no
                    // una corrección del médico, y no debe parecerlo.
                    $consulta->timestamps = false;
                    $consulta->update($limpios);
                }
            }
        });
    }

    public function down(): void
    {
        // Sin vuelta atrás a propósito.
    }
};
