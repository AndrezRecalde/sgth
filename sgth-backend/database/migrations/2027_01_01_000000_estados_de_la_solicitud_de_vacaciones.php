<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Los estados de la solicitud de vacaciones pasan a decir quién concede qué
| (decisión del usuario, 2026-11-24; ver App\Enums\EstadoVacacion):
|
|   pendiente -> autorizada   el jefe inmediato autorizó y firma el impreso
|   aprobada  -> registrada   Talento Humano la registró en ventanilla
|   rechazada -> anulada      queda una sola salida, siempre con motivo
|
| `gozada` y `anulada` no cambian.
|
| La columna nació con `enum()`, que en PostgreSQL es un varchar con un CHECK
| de sus valores: hay que rehacer el CHECK y el DEFAULT, no el tipo. Sin eso,
| el primer UPDATE falla con una violación de restricción.
*/
return new class extends Migration
{
    private const ANTES = ['pendiente', 'aprobada', 'rechazada', 'gozada', 'anulada'];

    private const AHORA = ['autorizada', 'registrada', 'gozada', 'anulada'];

    private const MOTIVO_DEL_RECHAZO = 'Rechazada por Talento Humano';

    public function up(): void
    {
        DB::transaction(function () {
            $this->soltarCheck();

            // El motivo, antes de cambiar el estado: después ya no se sabe
            // cuáles venían de un rechazo. `anulado_en` y `anulado_por` se
            // quedan en nulo a propósito: de un rechazo no se guardaba ni
            // quién ni cuándo, y una fecha deducida en un documento que se
            // archiva es peor que una ausente.
            DB::table('vacaciones')
                ->where('estado', 'rechazada')
                ->whereNull('motivo_anulacion')
                ->update(['motivo_anulacion' => self::MOTIVO_DEL_RECHAZO]);

            $this->renombrar(['pendiente' => 'autorizada', 'aprobada' => 'registrada', 'rechazada' => 'anulada']);

            $this->ponerCheck(self::AHORA, 'autorizada');
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $this->soltarCheck();

            // Las que fueron rechazadas se quedan anuladas: al convertirlas se
            // perdió la distinción y deducirla por el motivo devolvería al
            // estado equivocado cualquier anulación que alguien haya escrito
            // con ese mismo texto.
            $this->renombrar(['autorizada' => 'pendiente', 'registrada' => 'aprobada']);

            $this->ponerCheck(self::ANTES, 'pendiente');
        });
    }

    /** @param array<string, string> $mapa */
    private function renombrar(array $mapa): void
    {
        foreach ($mapa as $de => $a) {
            DB::table('vacaciones')->where('estado', $de)->update(['estado' => $a]);
        }
    }

    private function soltarCheck(): void
    {
        DB::statement('ALTER TABLE vacaciones DROP CONSTRAINT IF EXISTS vacaciones_estado_check');
        DB::statement('ALTER TABLE vacaciones ALTER COLUMN estado DROP DEFAULT');
    }

    /** @param list<string> $estados */
    private function ponerCheck(array $estados, string $porDefecto): void
    {
        $lista = implode(', ', array_map(fn (string $e) => "'{$e}'", $estados));

        DB::statement("ALTER TABLE vacaciones ALTER COLUMN estado SET DEFAULT '{$porDefecto}'");
        DB::statement("ALTER TABLE vacaciones ADD CONSTRAINT vacaciones_estado_check CHECK (estado IN ({$lista}))");
    }
};
