<?php

namespace App\Services\Autoservicio;

use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\MedioNotificacion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\MovimientoPersonal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * «Mis documentos laborales» (diseño, 9.13; TH 26; fase 6.3a): lo que el
 * servidor ve de sus actos en el portal, y su acuse de recibo.
 *
 * Sus acciones de personal desde que se le notifican —y las que se anularon
 * después, con su sello—; sus hojas de registro interno y constancias de
 * autoridad, que no se notifican, desde que se registran. Nunca un borrador
 * ni una suscrita: un acto que todavía no se emite no es suyo de ver (6.3).
 */
class MisDocumentosLaboralesService
{
    /** @return Collection<int, MovimientoPersonal> */
    public function listar(int $servidorId): Collection
    {
        return $this->visibles($servidorId)
            ->orderByDesc('fecha_efectiva')
            ->orderByDesc('id')
            ->get();
    }

    /** Uno de los suyos, o 404: el de otro no existe para él. */
    public function obtener(int $servidorId, int $movimientoId): MovimientoPersonal
    {
        return $this->visibles($servidorId)->findOrFail($movimientoId);
    }

    /**
     * El acuse de recibo, de lo que se le notificó por el portal. Una sola
     * vez: queda con la fecha y la hora en que lo dejó.
     */
    public function acusarRecibo(int $servidorId, int $movimientoId): MovimientoPersonal
    {
        $movimiento = $this->obtener($servidorId, $movimientoId);

        if ($movimiento->acuse_en !== null) {
            throw new ReglaNegocioException('Ya acusó recibo de este documento el '.$movimiento->acuse_en->format('d/m/Y H:i').'.');
        }

        if (! $this->puedeAcusar($movimiento)) {
            throw new ReglaNegocioException('Este documento no espera su acuse: no se le notificó por el portal.');
        }

        $movimiento->acuse_en = now();
        $movimiento->save();

        return $movimiento->fresh();
    }

    /** ¿Espera su acuse? Lo notificado por el portal, vigente y sin acuse. */
    public function puedeAcusar(MovimientoPersonal $movimiento): bool
    {
        return $movimiento->estado === EstadoAccionPersonal::NOTIFICADA
            && $movimiento->notificacion_medio === MedioNotificacion::PORTAL
            && $movimiento->acuse_en === null;
    }

    private function visibles(int $servidorId): Builder
    {
        return MovimientoPersonal::query()
            ->where('servidor_id', $servidorId)
            ->whereNotNull('codigo_registro')
            ->where(fn (Builder $q) => $q
                // La acción de personal, desde que se le notifica.
                ->where(fn (Builder $ap) => $ap
                    ->where('instrumento', InstrumentoActo::ACCION_PERSONAL->value)
                    ->whereNotNull('fecha_notificacion')
                    ->whereIn('estado', [EstadoAccionPersonal::NOTIFICADA->value, EstadoAccionPersonal::ANULADA->value]))
                // La hoja del obrero y la constancia de la autoridad no se
                // notifican: desde que se registran.
                ->orWhere(fn (Builder $otros) => $otros
                    ->whereIn('instrumento', [InstrumentoActo::DOCUMENTO_CT->value, InstrumentoActo::ACTO_AUTORIDAD->value])
                    ->whereIn('estado', [
                        EstadoAccionPersonal::REGISTRADA->value,
                        EstadoAccionPersonal::NOTIFICADA->value,
                        EstadoAccionPersonal::ANULADA->value,
                    ])));
    }
}
