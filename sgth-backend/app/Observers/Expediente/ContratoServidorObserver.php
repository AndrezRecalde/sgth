<?php
namespace App\Observers\Expediente;

use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use Illuminate\Support\Facades\Log;

class ContratoServidorObserver
{
    /**
     * Al crear un contrato nuevo.
     */
    public function creating(ContratoServidor $contrato): void
    {
        // Si el nuevo contrato es vigente, terminar el anterior
        if ($this->esVigente($contrato)) {
            $this->terminarContratosVigentes(
                $contrato->servidor_id,
                $contrato->id ?? null
            );
        }
    }

    /**
     * Al actualizar un contrato existente.
     */
    public function updating(ContratoServidor $contrato): void
    {
        // Si cambió a vigente, terminar los demás
        if ($contrato->isDirty('estado') && $this->esVigente($contrato)) {
            $this->terminarContratosVigentes(
                $contrato->servidor_id,
                $contrato->id
            );
        }
    }

    /**
     * Después de crear — sincronizar servidor.
     */
    public function created(ContratoServidor $contrato): void
    {
        if ($this->esVigente($contrato)) {
            $this->sincronizarServidor($contrato);
        }
    }

    /**
     * Después de actualizar — sincronizar servidor.
     */
    public function updated(ContratoServidor $contrato): void
    {
        if ($this->esVigente($contrato)) {
            $this->sincronizarServidor($contrato);
        }
    }

    /**
     * Al eliminar el contrato vigente buscar el
     * siguiente contrato más reciente y actualizar.
     */
    public function deleted(ContratoServidor $contrato): void
    {
        $contratoAnterior = ContratoServidor::where('servidor_id', $contrato->servidor_id)
            ->where('id', '!=', $contrato->id)
            ->whereNull('deleted_at')
            ->orderBy('fecha_inicio', 'desc')
            ->first();

        if ($contratoAnterior) {
            $this->sincronizarServidor($contratoAnterior);
        } else {
            try {
                $servidor = Servidor::find($contrato->servidor_id);
                if ($servidor) {
                    $servidor->update([
                        'unidad_administrativa_id' => null,
                        'puesto_id'                => null,
                        'codigo_marcacion'         => null,
                        'puede_marcar'             => false,
                        'tipo_nombramiento'        => null,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Error limpiando servidor tras eliminar contrato: " . $e->getMessage());
            }
        }
    }

    // ── Helpers ─────────────────────────────────────────

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

        $query->update(['estado' => 'terminado']);
    }

    private function sincronizarServidor(
        ContratoServidor $contrato
    ): void {
        try {
            $servidor = Servidor::find($contrato->servidor_id);
            if (!$servidor) return;

            $contrato->loadMissing('puesto');

            $datos = [
                'unidad_administrativa_id' => $contrato->unidad_administrativa_id,
                'puesto_id'                => $contrato->puesto_id,
                'codigo_marcacion'         => $contrato->codigo_marcacion ?? null,
                'puede_marcar'             => true,
            ];

            // Sync tipo_nombramiento desde el puesto del contrato
            if ($contrato->puesto) {
                $tipoNombramiento =
                    $contrato->puesto->tipo_nombramiento
                    ?? $contrato->puesto->nombre
                    ?? null;

                if ($tipoNombramiento) {
                    $datos['tipo_nombramiento'] = $tipoNombramiento;
                }
            }

            $servidor->update($datos);

            Log::info(
                "Servidor {$servidor->id} sincronizado " .
                "desde contrato {$contrato->id}",
                $datos
            );
        } catch (\Exception $e) {
            Log::error(
                "Error sincronizando servidor desde contrato: " .
                $e->getMessage()
            );
        }
    }
}
