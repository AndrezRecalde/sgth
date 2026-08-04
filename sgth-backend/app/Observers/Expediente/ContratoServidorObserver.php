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
     * Después de crear — sincronizar puede_marcar y dejar rastro en
     * activity_log.
     */
    public function created(ContratoServidor $contrato): void
    {
        if ($this->esVigente($contrato)) {
            $this->sincronizarPuedeMarcar($contrato);
        }

        activity()
            ->performedOn($contrato)
            ->withProperties([
                'tipo_nombramiento' => $contrato->tipo_nombramiento?->value,
                'estado'            => $contrato->estado?->value,
            ])
            ->event('created')
            ->log('Contrato de servidor creado');
    }

    /**
     * Después de actualizar — sincronizar puede_marcar y, si el contrato
     * quedó cerrado (manual vía cerrar() o automático al activarse uno
     * nuevo), dejar rastro en activity_log con quién y el motivo.
     */
    public function updated(ContratoServidor $contrato): void
    {
        if ($this->esVigente($contrato)) {
            $this->sincronizarPuedeMarcar($contrato);
        }

        if ($contrato->wasChanged('estado') && $contrato->estado?->value === 'terminado') {
            activity()
                ->performedOn($contrato)
                ->withProperties([
                    'motivo_fin' => $contrato->motivo_fin,
                    'fecha_fin'  => optional($contrato->fecha_fin)->toDateString(),
                ])
                ->event('updated')
                ->log('Contrato cerrado');
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

        // Update por instancia, no mass-update: un mass-update no dispara
        // eventos de modelo, y con ellos se perdería tanto sincronizarPuedeMarcar()
        // como la auditoría en activity_log de cada contrato cerrado
        // automáticamente. El volumen aquí siempre es 0 o 1 en la práctica
        // (un solo contrato vigente por servidor).
        $contratos = $query->get();

        if ($contratos->isEmpty()) {
            return;
        }

        foreach ($contratos as $contrato) {
            // Cierre, no edición libre: siempre queda fecha_fin + motivo_fin,
            // igual que un cierre manual vía ContratoServidorService::cerrar().
            $contrato->update([
                'estado'     => 'terminado',
                'fecha_fin'  => now()->toDateString(),
                'motivo_fin' => 'Reemplazado automáticamente por nuevo contrato vigente.',
            ]);
        }

        Log::info(
            "Terminados {$contratos->count()} contratos vigentes " .
            "del servidor {$servidorId} al activar nuevo contrato."
        );
    }

    private function sincronizarPuedeMarcar(
        ContratoServidor $contrato
    ): void {
        $puedeMarcar = (bool)($contrato->puede_marcar ?? true);

        // Update de instancia, no mass-update: mismo patrón que el resto
        // de esta fase — para que dispare ServidorObserver (limpieza de
        // caché incluida).
        \App\Models\Expediente\Servidor::findOrFail($contrato->servidor_id)
            ->update(['puede_marcar' => $puedeMarcar]);

        Log::info(
            "Servidor {$contrato->servidor_id}: " .
            "puede_marcar sincronizado a " .
            ($puedeMarcar ? 'true' : 'false')
        );
    }
}
