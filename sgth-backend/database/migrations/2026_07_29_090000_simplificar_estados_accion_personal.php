<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Se retiran los estados 'informe_uath' y 'dictamen_presupuestario'.
 *
 * No capturaban nada: pasar por ellos solo cambiaba una etiqueta, sin número
 * de informe, fecha, responsable ni documento. Confirmado con Talento Humano
 * (2026-07-29) que el flujo real es borrador → suscrita → registrada →
 * notificada, así que dos clics intermedios sin contenido solo estorbaban.
 *
 * La verificación presupuestaria no se pierde: sigue viva como guarda al
 * suscribir para los tipos con efecto económico, apoyada en
 * 'dictamen_presupuestario_ref' y en la disponibilidad de la partida. Lo que
 * desaparece es el estado, no el control.
 */
return new class extends Migration
{
    private const ESTADOS_VIGENTES = [
        'borrador', 'suscrita', 'registrada', 'notificada', 'anulada',
    ];

    private const ESTADOS_ANTERIORES = [
        'borrador', 'informe_uath', 'dictamen_presupuestario',
        'suscrita', 'registrada', 'notificada', 'anulada',
    ];

    public function up(): void
    {
        // Las acciones que estaban en un estado intermedio vuelven a borrador:
        // son las que aún no se firmaban, así que borrador es donde estaban
        // realmente en el trámite.
        $reubicadas = DB::table('movimientos_personal')
            ->whereIn('estado', ['informe_uath', 'dictamen_presupuestario'])
            ->update(['estado' => 'borrador']);

        if ($reubicadas > 0) {
            echo "  {$reubicadas} acción(es) en estados intermedios devueltas a borrador.\n";
        }

        $this->reemplazarCheck(self::ESTADOS_VIGENTES);
    }

    public function down(): void
    {
        $this->reemplazarCheck(self::ESTADOS_ANTERIORES);
    }

    /** @param  list<string>  $estados */
    private function reemplazarCheck(array $estados): void
    {
        $lista = implode(', ', array_map(fn ($e) => "'{$e}'", $estados));

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_estado_check');

        DB::statement("
            ALTER TABLE movimientos_personal
            ADD CONSTRAINT movimientos_personal_estado_check
            CHECK (estado IN ({$lista}))
        ");
    }
};
