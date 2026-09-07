<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `cancelado` y `rechazado` faltaban en la restricción de estados.
     *
     * El enum `EstadoViatico` los declara y el servicio los escribe:
     * `cancelar()` pone `cancelado` y `rechazar()` pone `rechazado`, y las dos
     * cuelgan de rutas vivas. Pero la restricción CHECK de la tabla solo admite
     * los siete estados del camino feliz, así que cancelar o rechazar un
     * viático terminaba en una violación de restricción —un 500— en vez de
     * hacerse.
     *
     * Se amplía la restricción en vez de quitar los estados del enum porque es
     * el código el que dice la intención: un viático que se solicita tiene que
     * poder rechazarse.
     */
    private const ESTADOS = [
        'solicitado', 'aprobado', 'con_anticipo', 'en_comision',
        'pendiente_liquidacion', 'liquidado', 'contabilizado',
        'cancelado', 'rechazado',
    ];

    public function up(): void
    {
        $this->reemplazarRestriccion(self::ESTADOS);
    }

    public function down(): void
    {
        // Al volver atrás, los viáticos que ya estén en los dos estados nuevos
        // impedirían recrear la restricción antigua. Se devuelven a
        // `solicitado`, que es de donde salieron.
        DB::table('viaticos')
            ->whereIn('estado', ['cancelado', 'rechazado'])
            ->update(['estado' => 'solicitado']);

        $this->reemplazarRestriccion(array_slice(self::ESTADOS, 0, 7));
    }

    /** @param list<string> $estados */
    private function reemplazarRestriccion(array $estados): void
    {
        $lista = implode(', ', array_map(
            fn (string $estado) => "'{$estado}'", $estados
        ));

        DB::statement('ALTER TABLE viaticos DROP CONSTRAINT IF EXISTS viaticos_estado_check');
        DB::statement(
            "ALTER TABLE viaticos ADD CONSTRAINT viaticos_estado_check "
            . "CHECK (estado::text = ANY (ARRAY[{$lista}]::text[]))"
        );
    }
};
