<?php
namespace App\Observers\Expediente;

use App\Models\Expediente\ContratoServidor;
use Illuminate\Support\Facades\Log;

class ContratoServidorObserver
{
    /**
     * Antes de crear: terminar contratos vigentes anteriores.
     */
    public function creating(ContratoServidor $contrato): void
    {
        if ($this->esVigente($contrato)) {
            $this->terminarContratosVigentes(
                $contrato->servidor_id,
                null
            );
        }
    }

    /**
     * Antes de actualizar: si cambia a vigente,
     * terminar los demás.
     */
    public function updating(ContratoServidor $contrato): void
    {
        if (
            $contrato->isDirty('estado') &&
            $this->esVigente($contrato)
        ) {
            $this->terminarContratosVigentes(
                $contrato->servidor_id,
                $contrato->id
            );
        }
    }

    /**
     * Después de crear — sincronizar puede_marcar.
     */
    public function created(ContratoServidor $contrato): void
    {
        if ($this->esVigente($contrato)) {
            $this->sincronizarPuedeMarcar($contrato);
        }
    }

    /**
     * Después de actualizar — sincronizar puede_marcar.
     */
    public function updated(ContratoServidor $contrato): void
    {
        if ($this->esVigente($contrato)) {
            $this->sincronizarPuedeMarcar($contrato);
        }
    }

    // ── Helpers ──────────────────────────────────────────

    private function esVigente(ContratoServidor $contrato): bool
    {
        $estado = $contrato->estado instanceof \App\Enums\EstadoContrato
            ? $contrato->estado->value
            : (string)($contrato->estado ?? '');

        return $estado === 'vigente';
    }

    private function terminarContratosVigentes(
        int $servidorId,
        ?int $excluirId
    ): void {
        $query = ContratoServidor::where('servidor_id', $servidorId)
            ->where('estado', 'vigente');

        if ($excluirId) {
            $query->where('id', '!=', $excluirId);
        }

        $count = $query->count();

        if ($count > 0) {
            $query->update(['estado' => 'terminado']);
            Log::info(
                "Terminados {$count} contratos vigentes " .
                "del servidor {$servidorId} al activar nuevo contrato."
            );
        }
    }

    private function sincronizarPuedeMarcar(
        ContratoServidor $contrato
    ): void {
        $puedeMarcar = (bool)($contrato->puede_marcar ?? true);

        \App\Models\Expediente\Servidor::where(
            'id', $contrato->servidor_id
        )->update(['puede_marcar' => $puedeMarcar]);

        Log::info(
            "Servidor {$contrato->servidor_id}: " .
            "puede_marcar sincronizado a " .
            ($puedeMarcar ? 'true' : 'false')
        );
    }
}
