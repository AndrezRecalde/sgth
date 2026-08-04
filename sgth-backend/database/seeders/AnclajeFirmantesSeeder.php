<?php

namespace Database\Seeders;

use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Database\Seeder;

/**
 * Marca de arranque las unidades de las que salen los firmantes de las
 * Acciones de Personal: la máxima autoridad y Talento Humano.
 *
 * Es solo un valor inicial. La inferencia se hace por el cargo del puesto de
 * jefatura —que es el dato más específico disponible— y **debe verificarse**:
 * quién firma los documentos legales no puede depender de una coincidencia de
 * texto. Una vez marcadas, se cambian desde Estructura → Unidades y este
 * seeder no vuelve a tocarlas.
 */
class AnclajeFirmantesSeeder extends Seeder
{
    public function run(): void
    {
        $this->anclar('es_maxima_autoridad', '%prefect%', 'máxima autoridad');
        $this->anclar('es_unidad_talento_humano', '%talento humano%', 'Talento Humano');
    }

    private function anclar(string $bandera, string $patronCargo, string $etiqueta): void
    {
        if (UnidadAdministrativa::where($bandera, true)->exists()) {
            $this->command?->info("Anclaje de {$etiqueta} ya definido — no se modifica.");

            return;
        }

        $candidatas = UnidadAdministrativa::whereHas(
            'puestos',
            fn ($q) => $q->where('es_jefe', true)
                ->whereHas('cargo', fn ($c) => $c->where('nombre', 'ilike', $patronCargo))
        )->get();

        if ($candidatas->count() !== 1) {
            $this->command?->warn(
                "No se pudo anclar {$etiqueta} automáticamente ("
                    .$candidatas->count().' unidad(es) candidata(s)). '
                    .'Márquela a mano en Estructura → Unidades.'
            );

            return;
        }

        $unidad = $candidatas->first();
        $unidad->update([$bandera => true]);

        $this->command?->info("Anclaje de {$etiqueta}: {$unidad->nombre}. Verifíquelo.");
    }
}
