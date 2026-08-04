<?php

namespace App\Services\Disciplinario;

use App\Enums\EstadoVistoBueno;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Disciplinario\VistoBueno;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\MovimientoPersonalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trámite de visto bueno ante el Inspector del Trabajo (Art. 172 y 183 del
 * Código del Trabajo). El sistema no resuelve nada: registra lo que resolvió
 * el Ministerio del Trabajo, y si concedió el visto bueno genera la Cesación
 * de Funciones correspondiente en borrador para que Talento Humano la revise.
 */
class VistoBuenoService
{
    public function __construct(
        private readonly MovimientoPersonalService $movimientoPersonalService,
    ) {
    }

    /**
     * Grafo de transiciones. DESISTIDO se alcanza desde cualquier estado no
     * resuelto (la institución puede retirar la solicitud mientras el
     * Inspector no se pronuncie); IMPUGNADO solo desde una resolución.
     */
    private const TRANSICIONES = [
        'solicitado'       => ['notificado', 'desistido'],
        'notificado'       => ['en_investigacion', 'desistido'],
        'en_investigacion' => ['concedido', 'negado', 'desistido'],
        'concedido'        => ['impugnado'],
        'negado'           => ['impugnado'],
        'desistido'        => [],
        'impugnado'        => [],
    ];

    /**
     * Plazos del Art. 183 del Código del Trabajo: el Inspector notifica al
     * trabajador dentro de las 24 horas de presentada la solicitud, e
     * investiga y resuelve dentro de los tres días siguientes. Se expresan en
     * días hábiles y se cuentan como alerta, no como bloqueo: el sistema no
     * puede impedir que el Ministerio se demore, solo advertirlo.
     */
    private const DIAS_HABILES_NOTIFICACION = 1;
    private const DIAS_HABILES_RESOLUCION   = 3;

    /**
     * Trámites que excedieron un plazo legal. Devuelve el detalle en vez de
     * solo escribirlo en el log, para que el job pueda notificar y la API
     * pueda mostrarlo en la bandeja.
     *
     * @return list<array{visto_bueno_id:int, servidor_id:int, plazo:string, fecha_limite:string, dias_vencido:int}>
     */
    public function controlarPlazosLegales(): array
    {
        $hoy      = Carbon::today();
        $alertas  = [];

        $pendientesNotificacion = VistoBueno::where('estado', EstadoVistoBueno::SOLICITADO->value)->get();

        foreach ($pendientesNotificacion as $tramite) {
            $limite = $this->sumarDiasHabiles(
                Carbon::parse($tramite->fecha_solicitud),
                self::DIAS_HABILES_NOTIFICACION
            );

            if ($hoy->gt($limite)) {
                $alertas[] = $this->alerta($tramite, 'notificacion', $limite, $hoy);
            }
        }

        $pendientesResolucion = VistoBueno::whereIn('estado', [
            EstadoVistoBueno::NOTIFICADO->value,
            EstadoVistoBueno::EN_INVESTIGACION->value,
        ])->whereNotNull('fecha_notificacion')->get();

        foreach ($pendientesResolucion as $tramite) {
            $limite = $this->sumarDiasHabiles(
                Carbon::parse($tramite->fecha_notificacion),
                self::DIAS_HABILES_RESOLUCION
            );

            if ($hoy->gt($limite)) {
                $alertas[] = $this->alerta($tramite, 'resolucion', $limite, $hoy);
            }
        }

        foreach ($alertas as $alerta) {
            Log::warning(
                "Visto bueno #{$alerta['visto_bueno_id']} excedió el plazo de {$alerta['plazo']} "
                    ."(límite {$alerta['fecha_limite']}, {$alerta['dias_vencido']} día(s) de retraso)."
            );
        }

        return $alertas;
    }

    /** @return array{visto_bueno_id:int, servidor_id:int, plazo:string, fecha_limite:string, dias_vencido:int} */
    private function alerta(VistoBueno $tramite, string $plazo, Carbon $limite, Carbon $hoy): array
    {
        return [
            'visto_bueno_id' => $tramite->id,
            'servidor_id'    => $tramite->servidor_id,
            'plazo'          => $plazo,
            'fecha_limite'   => $limite->toDateString(),
            'dias_vencido'   => (int) $limite->diffInDays($hoy),
        ];
    }

    /**
     * Suma días hábiles salteando sábados y domingos. No contempla feriados:
     * el módulo de feriados vive en Asistencia y depende del año cargado, así
     * que para una alerta (no un bloqueo) el fin de semana es suficiente.
     */
    private function sumarDiasHabiles(Carbon $desde, int $dias): Carbon
    {
        $fecha = $desde->copy();

        while ($dias > 0) {
            $fecha->addDay();

            if (!$fecha->isWeekend()) {
                $dias--;
            }
        }

        return $fecha;
    }

    public function solicitar(int $servidorId, array $datos, int $userId): VistoBueno
    {
        $servidor = Servidor::with('contratoVigente')->findOrFail($servidorId);

        $this->assertRegimenCodigoTrabajo($servidor);
        $this->assertSinTramiteAbierto($servidorId);

        return VistoBueno::create([
            ...$datos,
            'servidor_id' => $servidorId,
            'estado'      => EstadoVistoBueno::SOLICITADO,
            'created_by'  => $userId,
            'updated_by'  => $userId,
        ]);
    }

    public function transicionar(
        VistoBueno $vistoBueno,
        EstadoVistoBueno $destino,
        array $datos,
        int $userId
    ): VistoBueno {
        $this->assertTransicionPermitida($vistoBueno->estado, $destino);

        return DB::transaction(function () use ($vistoBueno, $destino, $datos, $userId) {
            match ($destino) {
                EstadoVistoBueno::NOTIFICADO => $this->aplicarNotificacion($vistoBueno, $datos),
                EstadoVistoBueno::CONCEDIDO,
                EstadoVistoBueno::NEGADO     => $this->aplicarResolucion($vistoBueno, $datos),
                default                      => null,
            };

            $vistoBueno->estado     = $destino;
            $vistoBueno->updated_by = $userId;
            $vistoBueno->save();

            if ($destino === EstadoVistoBueno::CONCEDIDO) {
                $this->generarCesacion($vistoBueno);
            }

            return $vistoBueno->fresh(['servidor', 'movimientoPersonal']);
        });
    }

    /**
     * El visto bueno es del régimen de Código del Trabajo. Un servidor LOSEP se
     * sanciona por sumario administrativo — el espejo de la validación en
     * DisciplinarioService::assertSancionAplicableAlRegimen().
     */
    private function assertRegimenCodigoTrabajo(Servidor $servidor): void
    {
        $nombramiento = $servidor->contratoVigente?->tipo_nombramiento;

        if (!$nombramiento instanceof TipoNombramiento) {
            throw new ReglaNegocioException(
                'El servidor no tiene un contrato vigente con tipo de nombramiento definido.'
            );
        }

        if ($nombramiento !== TipoNombramiento::CODIGO_TRABAJO) {
            throw new ReglaNegocioException(
                'El visto bueno solo aplica a obreros bajo Código del Trabajo. '
                    ."Este servidor es {$nombramiento->etiqueta()}: corresponde un sumario administrativo."
            );
        }
    }

    private function assertSinTramiteAbierto(int $servidorId): void
    {
        $abierto = VistoBueno::where('servidor_id', $servidorId)
            ->whereIn('estado', [
                EstadoVistoBueno::SOLICITADO->value,
                EstadoVistoBueno::NOTIFICADO->value,
                EstadoVistoBueno::EN_INVESTIGACION->value,
            ])
            ->exists();

        if ($abierto) {
            throw new ReglaNegocioException(
                'El servidor ya tiene un trámite de visto bueno en curso.'
            );
        }
    }

    private function assertTransicionPermitida(
        EstadoVistoBueno $origen,
        EstadoVistoBueno $destino
    ): void {
        $permitidas = self::TRANSICIONES[$origen->value] ?? [];

        if (!in_array($destino->value, $permitidas, true)) {
            throw new ReglaNegocioException(
                "No se puede pasar de '{$origen->etiqueta()}' a '{$destino->etiqueta()}'."
            );
        }
    }

    private function aplicarNotificacion(VistoBueno $vistoBueno, array $datos): void
    {
        $vistoBueno->fecha_notificacion = $datos['fecha_notificacion'] ?? now()->toDateString();
        $vistoBueno->numero_tramite_mdt = $datos['numero_tramite_mdt'] ?? $vistoBueno->numero_tramite_mdt;
        $vistoBueno->inspectoria        = $datos['inspectoria'] ?? $vistoBueno->inspectoria;
        $vistoBueno->inspector_nombre   = $datos['inspector_nombre'] ?? $vistoBueno->inspector_nombre;
    }

    /**
     * La resolución del Inspector es el documento que sustenta todo lo que
     * viene después, así que su detalle es obligatorio: sin él, una cesación
     * generada más abajo quedaría sin respaldo verificable.
     */
    private function aplicarResolucion(VistoBueno $vistoBueno, array $datos): void
    {
        if (empty($datos['resolucion_detalle'])) {
            throw new ReglaNegocioException(
                'Debe registrarse el detalle de la resolución emitida por el Inspector del Trabajo.'
            );
        }

        $vistoBueno->resolucion_detalle = $datos['resolucion_detalle'];
        $vistoBueno->fecha_resolucion   = $datos['fecha_resolucion'] ?? now()->toDateString();
        $vistoBueno->documento_respaldo = $datos['documento_respaldo'] ?? $vistoBueno->documento_respaldo;
    }

    /**
     * Concedido el visto bueno, se genera la Cesación de Funciones en
     * BORRADOR: el vínculo no se cierra aquí. Talento Humano la revisa y la
     * lleva por el flujo normal de acciones de personal, que es donde el
     * contrato se cierra de verdad (MovimientoPersonalStateService).
     */
    private function generarCesacion(VistoBueno $vistoBueno): void
    {
        $movimiento = $this->movimientoPersonalService->registrar($vistoBueno->servidor_id, [
            'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
            'subtipo_movimiento' => SubtipoMovimientoPersonal::VISTO_BUENO->value,
            'descripcion'        => 'Terminación del contrato por visto bueno concedido — '
                .$vistoBueno->causal->referenciaLegal()
                .'. Trámite '.($vistoBueno->numero_tramite_mdt ?: "interno #{$vistoBueno->id}").'.',
            'fecha_efectiva'     => $vistoBueno->fecha_resolucion?->toDateString() ?? now()->toDateString(),
            'resolucion_numero'  => $vistoBueno->numero_tramite_mdt,
        ]);

        $vistoBueno->movimiento_personal_id = $movimiento->id;
        $vistoBueno->save();
    }
}
