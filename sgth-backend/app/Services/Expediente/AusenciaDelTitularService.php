<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\MotivoSubrogacion;
use App\Enums\TipoAusenciaTitular;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\Licencia;
use App\Models\Asistencia\Vacacion;
use App\Models\Dispensario\CertificadoMedico;
use App\Models\Expediente\MovimientoPersonal;
use Carbon\Carbon;

/**
 * Las ausencias de un titular que pueden motivar su subrogación (LOSEP, Art.
 * 126: «legalmente ausente»; diseño, 9.5; fase 5.2): vacaciones aprobadas, una
 * licencia con remuneración, un reposo del Dispensario y una comisión o
 * licencia por acción de personal.
 *
 * La subrogación se enlaza con una de ellas, cabe dentro de sus fechas y la
 * sigue: si la ausencia se anula, se cancela; si un reintegro la acorta,
 * termina la víspera.
 */
class AusenciaDelTitularService
{
    /** Los estados de las vacaciones que ausentan de verdad al titular. */
    private const VACACIONES_VIGENTES = ['aprobada', 'gozada'];

    /**
     * Las ausencias del titular que tocan esas fechas; sin fechas, las que no
     * han terminado. De la que empieza antes a la que empieza después.
     *
     * @return list<array{tipo: string, id: int, etiqueta: string, desde: string, hasta: ?string, motivo: string}>
     */
    public function listar(int $servidorId, ?string $desde = null, ?string $hasta = null): array
    {
        $desde = $desde ? Carbon::parse($desde)->toDateString() : now()->toDateString();
        $tope = $hasta ? Carbon::parse($hasta)->toDateString() : '9999-12-31';

        $vacaciones = Vacacion::where('servidor_id', $servidorId)
            ->whereIn('estado', self::VACACIONES_VIGENTES)
            ->whereDate('fecha_fin', '>=', $desde)
            ->whereDate('fecha_inicio', '<=', $tope)
            ->get()
            ->map(fn (Vacacion $v) => $this->deVacacion($v));

        $licencias = Licencia::vigentesDe($servidorId, $desde, $tope)
            ->get()
            ->map(fn (Licencia $l) => $this->deLicencia($l));

        $reposos = CertificadoMedico::reposoDe($servidorId, $desde, $tope)
            ->get()
            ->map(fn (CertificadoMedico $c) => $this->deReposo($c));

        $acciones = MovimientoPersonal::query()->esAusenciaTemporal()
            ->where('servidor_id', $servidorId)
            ->whereIn('estado', [EstadoAccionPersonal::REGISTRADA->value, EstadoAccionPersonal::NOTIFICADA->value])
            ->whereDate('fecha_inicio', '<=', $tope)
            ->get()
            ->map(fn (MovimientoPersonal $m) => $this->deAccion($m))
            ->filter(fn (array $a) => $a['hasta'] === null || $a['hasta'] >= $desde);

        return $vacaciones->concat($licencias)->concat($reposos)->concat($acciones)
            ->map(fn (array $a) => [
                'tipo'     => $a['tipo'],
                'id'       => $a['id'],
                'etiqueta' => $a['etiqueta'],
                'desde'    => $a['desde'],
                'hasta'    => $a['hasta'],
                'motivo'   => $a['motivo']->value,
            ])
            ->sortBy([['desde', 'asc'], ['id', 'asc']])
            ->values()
            ->all();
    }

    /**
     * La ausencia elegida, si es del titular y sigue en pie.
     *
     * @return array{tipo: string, id: int, etiqueta: string, desde: string, hasta: ?string, motivo: MotivoSubrogacion}
     */
    public function resolver(TipoAusenciaTitular $tipo, int $id, int $servidorId): array
    {
        $ausencia = match ($tipo) {
            TipoAusenciaTitular::VACACION => ($v = Vacacion::find($id))
                && $v->servidor_id === $servidorId && in_array($v->estado, self::VACACIONES_VIGENTES, true)
                ? $this->deVacacion($v) : null,
            TipoAusenciaTitular::LICENCIA => ($l = Licencia::find($id))
                && $l->servidor_id === $servidorId && $l->anulado_en === null
                ? $this->deLicencia($l) : null,
            TipoAusenciaTitular::REPOSO => ($c = CertificadoMedico::find($id))
                && (int) $c->servidor_id === $servidorId && $c->anulado_en === null
                ? $this->deReposo($c) : null,
            TipoAusenciaTitular::ACCION_PERSONAL => ($m = MovimientoPersonal::find($id))
                && $m->servidor_id === $servidorId && $m->esAusenciaTemporal()
                && in_array($m->estado, [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA], true)
                ? $this->deAccion($m) : null,
            TipoAusenciaTitular::OTRA => null,
        };

        return $ausencia ?? throw new ReglaNegocioException(
            'La ausencia elegida no es del titular, o ya no está en pie: las vacaciones tienen que estar aprobadas, '
                .'y la licencia, el reposo o la acción, sin anular.'
        );
    }

    // ── La forma común ───────────────────────────────────────────────

    private function deVacacion(Vacacion $v): array
    {
        return $this->forma(TipoAusenciaTitular::VACACION, $v->id, 'Vacaciones '.($v->folio ?? "#{$v->id}"),
            $v->fecha_inicio, $v->fecha_fin, MotivoSubrogacion::VACACIONES);
    }

    private function deLicencia(Licencia $l): array
    {
        return $this->forma(TipoAusenciaTitular::LICENCIA, $l->id, "Licencia {$l->folio} ({$l->causa->etiqueta()})",
            $l->fecha_inicio, $l->fecha_fin, MotivoSubrogacion::LICENCIA);
    }

    private function deReposo(CertificadoMedico $c): array
    {
        return $this->forma(TipoAusenciaTitular::REPOSO, $c->id, "Reposo médico {$c->folio}",
            $c->fecha_inicio, $c->fecha_fin, MotivoSubrogacion::ENFERMEDAD);
    }

    private function deAccion(MovimientoPersonal $m): array
    {
        $comision = $m->subtipoEfectivo()?->esComisionDeServicios() ?? false;

        return $this->forma(TipoAusenciaTitular::ACCION_PERSONAL, $m->id,
            trim(($m->etiquetaAusencia() ?? $m->etiqueta()).' '.($m->codigo_registro ?? '')),
            $m->fecha_inicio ?? $m->fecha_efectiva, $m->finEfectivo(),
            $comision ? MotivoSubrogacion::COMISION : MotivoSubrogacion::LICENCIA);
    }

    private function forma(TipoAusenciaTitular $tipo, int $id, string $etiqueta, $desde, $hasta, MotivoSubrogacion $motivo): array
    {
        return [
            'tipo'     => $tipo->value,
            'id'       => $id,
            'etiqueta' => $etiqueta,
            'desde'    => Carbon::parse($desde)->toDateString(),
            'hasta'    => $hasta ? Carbon::parse($hasta)->toDateString() : null,
            'motivo'   => $motivo,
        ];
    }
}
