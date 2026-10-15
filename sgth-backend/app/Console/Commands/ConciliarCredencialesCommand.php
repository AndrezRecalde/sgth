<?php

namespace App\Console\Commands;

use App\Contracts\Comunicacion\CredencialServiceInterface;
use Illuminate\Console\Command;

/**
 * Pone al día las credenciales con el vínculo y la foto de cada servidor
 * (docs/diseno-comunicacion-social.md, 6.3; fase 3.1).
 *
 * El día a día lo hacen la foto y el cambio de vínculo al momento. Esto
 * recoge lo que solo depende de la fecha —la credencial que vence con el
 * contrato— y, después de migrar, crea las de quienes ya tenían foto.
 */
class ConciliarCredencialesCommand extends Command
{
    protected $signature = 'sgth:credenciales:conciliar';

    protected $description = 'Crea, vence, reactiva o anula las credenciales de acuerdo con el vínculo y la foto de cada servidor.';

    public function handle(CredencialServiceInterface $credenciales): int
    {
        $cuentas = $credenciales->conciliar();

        $this->table(
            ['Creadas', 'Vencidas', 'Reactivadas', 'Anuladas'],
            [[$cuentas['creadas'], $cuentas['vencidas'], $cuentas['reactivadas'], $cuentas['anuladas']]],
        );

        return self::SUCCESS;
    }
}
