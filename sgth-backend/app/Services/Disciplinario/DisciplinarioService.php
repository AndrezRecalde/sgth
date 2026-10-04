<?php

namespace App\Services\Disciplinario;

use App\Contracts\Disciplinario\DisciplinarioServiceInterface;
use App\Enums\EstadoSumario;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Enums\TipoSancion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Disciplinario\SancionDisciplinaria;
use App\Models\Disciplinario\Sumario;
use App\Models\Expediente\Servidor;
use App\Helpers\DiasHabilesHelper;
use App\Services\Expediente\MovimientoPersonalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class DisciplinarioService implements DisciplinarioServiceInterface
{
    use DiasHabilesHelper;

    public function __construct(
        private readonly MovimientoPersonalService $movimientoPersonalService,
    ) {
    }

    /**
     * Secuencia procesal del sumario. No es "cualquier estado hacia adelante":
     * se avanza hito por hito, y RESUELTO se alcanza solo vía
     * resolverSumario(), que es donde se aplica la sanción.
     */
    /**
     * Los plazos procesales del sumario, en días hábiles. Son los que la
     * migración de `sumarios` anota columna por columna, y hasta ahora vivían
     * como números sueltos dentro del control de plazos.
     */
    private const DIAS_HABILES_NOTIFICACION = 3;
    private const DIAS_HABILES_PRUEBA       = 5;
    private const DIAS_HABILES_INFORME      = 3;
    private const DIAS_HABILES_RESOLUCION   = 10;

    private const TRANSICIONES_SUMARIO = [
        'abierto'        => ['en_instruccion', 'cerrado'],
        'en_instruccion' => ['en_prueba', 'cerrado'],
        'en_prueba'      => ['con_informe', 'cerrado'],
        'con_informe'    => ['cerrado'],
        'resuelto'       => ['apelado', 'cerrado'],
        'apelado'        => ['cerrado'],
        'cerrado'        => [],
    ];

    public function abrirSumario(int $servidorId, array $datos, int $userId): Sumario
    {
        $servidor = Servidor::with('contratoVigente')->findOrFail($servidorId);
        $nombramiento = $servidor->contratoVigente?->tipo_nombramiento;

        if ($nombramiento === TipoNombramiento::CODIGO_TRABAJO) {
            throw new ReglaNegocioException(
                'El sumario administrativo es el procedimiento de la LOSEP. Para un obrero '
                    .'bajo Código del Trabajo, tramite un visto bueno ante el Inspector del Trabajo.'
            );
        }

        $tieneAbierto = Sumario::where('servidor_id', $servidorId)
            ->whereNotIn('estado', [EstadoSumario::RESUELTO->value, EstadoSumario::CERRADO->value])
            ->exists();

        if ($tieneAbierto) {
            throw new ReglaNegocioException('El servidor ya tiene un sumario administrativo en curso.');
        }

        return Sumario::create([
            ...$datos,
            'servidor_id'    => $servidorId,
            'estado'         => EstadoSumario::ABIERTO,
            'fecha_apertura' => $datos['fecha_apertura'] ?? now()->toDateString(),
            'notificado_sn'  => false,
            'created_by'     => $userId,
            'updated_by'     => $userId,
        ]);
    }

    public function avanzarSumario(Sumario $sumario, string $estadoDestino, array $datos, int $userId): Sumario
    {
        $permitidas = self::TRANSICIONES_SUMARIO[$sumario->estado->value] ?? [];

        if (!in_array($estadoDestino, $permitidas, true)) {
            throw new ReglaNegocioException(
                "No se puede pasar de '{$sumario->estado->value}' a '{$estadoDestino}'."
            );
        }

        $destino = EstadoSumario::from($estadoDestino);

        $this->validarCronologia($sumario, $destino, $datos);

        // Cada hito procesal deja su fecha: son las que alimentan el control
        // de plazos legales de controlarPlazosLegales().
        match ($destino) {
            EstadoSumario::EN_INSTRUCCION => $this->marcarNotificacion($sumario, $datos),
            EstadoSumario::EN_PRUEBA      => $this->marcarTerminoDePrueba($sumario, $datos),
            EstadoSumario::CON_INFORME    => $sumario->fecha_informe = $datos['fecha_informe'] ?? now()->toDateString(),
            default                       => null,
        };

        $sumario->estado     = $destino;
        $sumario->updated_by = $userId;
        $sumario->save();

        return $sumario->fresh(['servidor', 'sancion']);
    }

    /**
     * Cada hito va después del anterior (2026-10-04). Se guardaba cualquier
     * fecha: una notificación anterior a la apertura, o el informe del
     * instructor antes de que terminara el período de prueba —que cierra la
     * instrucción sin dejar al sumariado el plazo para presentar pruebas—.
     * Los plazos legales que vigila controlarPlazosLegales() salen de estas
     * fechas, así que un orden imposible daba alertas sin sentido.
     *
     * El error va en el campo del hito, para que la pantalla lo ponga bajo
     * la fecha.
     */
    private function validarCronologia(Sumario $sumario, EstadoSumario $destino, array $datos): void
    {
        [$campo, $fecha, $previa, $mensaje] = match ($destino) {
            EstadoSumario::EN_INSTRUCCION => [
                'fecha_notificacion',
                $datos['fecha_notificacion'] ?? null,
                $sumario->fecha_apertura,
                'La notificación no puede ser anterior a la apertura del sumario (%s).',
            ],
            EstadoSumario::EN_PRUEBA => [
                'fecha_termino_prueba',
                $datos['fecha_termino_prueba'] ?? null,
                $sumario->fecha_notificacion ?? $sumario->fecha_apertura,
                'El período de prueba no puede terminar antes de la notificación (%s).',
            ],
            EstadoSumario::CON_INFORME => [
                'fecha_informe',
                $datos['fecha_informe'] ?? now()->toDateString(),
                $sumario->fecha_termino_prueba,
                'El informe del instructor va después del período de prueba, que termina el %s.',
            ],
            default => [null, null, null, null],
        };

        if ($campo === null || $fecha === null || $previa === null) {
            return;
        }

        $previa = Carbon::parse($previa)->startOfDay();
        if (Carbon::parse($fecha)->startOfDay()->lt($previa)) {
            throw ValidationException::withMessages([
                $campo => sprintf($mensaje, $previa->format('d/m/Y')),
            ]);
        }
    }

    private function marcarNotificacion(Sumario $sumario, array $datos): void
    {
        $sumario->notificado_sn      = true;
        $sumario->fecha_notificacion = $datos['fecha_notificacion'] ?? now()->toDateString();
    }

    /**
     * Abierto el período de prueba, su término queda fijado: si no viene en la
     * petición se cuentan los 5 días hábiles desde la notificación.
     *
     * Antes era `$datos['fecha_termino_prueba'] ?? null`, y la pantalla solo
     * manda el estado, así que la columna quedaba NULL siempre: el plazo que
     * la propia migración documenta no se registraba ni se podía vigilar.
     */
    private function marcarTerminoDePrueba(Sumario $sumario, array $datos): void
    {
        if (!empty($datos['fecha_termino_prueba'])) {
            $sumario->fecha_termino_prueba = $datos['fecha_termino_prueba'];

            return;
        }

        $desde = $sumario->fecha_notificacion ?? $sumario->fecha_apertura;

        $sumario->fecha_termino_prueba = $this
            ->calcularDiasHabiles(Carbon::parse($desde), self::DIAS_HABILES_PRUEBA)
            ->toDateString();
    }

    public function resolverSumario(int $sumarioId, array $datosSancion, int $userId): Sumario
    {
        $sumario = Sumario::findOrFail($sumarioId);

        if ($sumario->estado === EstadoSumario::RESUELTO || $sumario->estado === EstadoSumario::CERRADO) {
            throw new ReglaNegocioException('El sumario ya se encuentra resuelto o cerrado.');
        }

        // Un sumario apelado tampoco se vuelve a resolver: su única salida es
        // el cierre. El guard de arriba lo dejaba pasar, y como
        // `sanciones_disciplinarias.sumario_id` es único, la segunda sanción
        // reventaba con un error de SQL en vez de un mensaje de negocio.
        if ($sumario->estado === EstadoSumario::APELADO) {
            throw new ReglaNegocioException(
                'El sumario está apelado: lo que resuelve la apelación es el cierre del sumario, '
                    .'no una sanción nueva. Si la sanción impuesta estaba equivocada, se anula el '
                    .'acto y se emite otro.'
            );
        }

        // Cinturón y tirantes: el índice único sigue ahí, y una sanción
        // borrada lógicamente lo ocupa igual, así que un sumario que ya tuvo
        // sanción no admite otra por mucho que su estado diga lo contrario.
        if ($sumario->sancion()->withTrashed()->exists()) {
            throw new ReglaNegocioException(
                'El sumario ya tiene una sanción registrada.'
            );
        }

        $this->assertSancionAplicableAlRegimen($sumario, $datosSancion['tipo_sancion']);

        DB::beginTransaction();
        try {
            $sumario->estado = EstadoSumario::RESUELTO;
            $sumario->fecha_resolucion = now()->toDateString();
            $sumario->updated_by = $userId;
            $sumario->save();

            SancionDisciplinaria::create([
                'sumario_id'       => $sumario->id,
                'tipo_falta'       => $datosSancion['tipo_falta'],
                'tipo_sancion'     => $datosSancion['tipo_sancion'],
                'porcentaje_multa' => $datosSancion['porcentaje_multa'] ?? null,
                'dias_suspension'  => $datosSancion['dias_suspension'] ?? null,
                'fecha_efectiva'   => $datosSancion['fecha_efectiva'] ?? now()->toDateString(),
                'observaciones'    => $datosSancion['observaciones'] ?? null,
                'created_by'       => $userId,
            ]);

            // Regla de Negocio: Si la sanción es Destitución, registrar el egreso.
            //
            // En BORRADOR, y sin tocar al servidor. El vínculo lo cierra la
            // acción de personal al registrarse (MovimientoPersonalStateService
            // ::cerrarVinculo), que es también la única que sabe reabrirlo si
            // alguien la anula. Desactivar aquí al servidor lo sacaba de la
            // nómina y de la generación de períodos de vacaciones —las dos
            // consultan `Servidor::where('estado', true)`— mientras el acto
            // todavía era un borrador que Talento Humano podía rechazar, y
            // anularlo no lo devolvía: `deshacerCierreDeVinculo()` reabre el
            // contrato, pero nadie vuelve a poner `estado = true` porque en el
            // flujo normal nadie lo había puesto en false. La vía del visto
            // bueno, mismo resultado y mismo régimen de aprobación, nunca lo
            // tocó.
            if ($datosSancion['tipo_sancion'] === TipoSancion::DESTITUCION->value) {
                // La destitución es una cesación de funciones cuyo subtipo es
                // 'destitucion' — el sumario es su causa, no su tipo. Se
                // registra así desde la taxonomía de dos niveles; el tipo
                // plano 'destitucion' queda solo para el histórico anterior.
                $this->movimientoPersonalService->registrar($sumario->servidor_id, [
                    'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
                    'subtipo_movimiento' => SubtipoMovimientoPersonal::DESTITUCION->value,
                    'descripcion'        => 'Destitución por sanción disciplinaria en Sumario Administrativo #' . $sumario->id,
                    'fecha_efectiva'     => $datosSancion['fecha_efectiva'] ?? now()->toDateString(),
                ]);
            }

            DB::commit();

            return $sumario;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * El sumario administrativo es el procedimiento de la LOSEP. A un obrero
     * bajo Código del Trabajo no se le destituye por esta vía: la terminación
     * con justa causa se tramita como visto bueno ante el Inspector del
     * Trabajo (Art. 172). Se valida al entrar, antes de abrir la transacción,
     * para no dejar el sumario a medio resolver ni devolver un mensaje que
     * hable de "tipo de nombramiento" sin decir cuál es la vía correcta.
     */
    private function assertSancionAplicableAlRegimen(Sumario $sumario, string $tipoSancion): void
    {
        if ($tipoSancion !== TipoSancion::DESTITUCION->value) {
            return;
        }

        $servidor = Servidor::with('contratoVigente')->findOrFail($sumario->servidor_id);
        $nombramiento = $servidor->contratoVigente?->tipo_nombramiento;

        if ($nombramiento === TipoNombramiento::CODIGO_TRABAJO) {
            throw new ReglaNegocioException(
                'Los obreros bajo Código del Trabajo no se destituyen por sumario administrativo. '
                    .'Tramite un visto bueno ante el Inspector del Trabajo desde el módulo Disciplinario.'
            );
        }
    }

    /**
     * Sumarios que excedieron un plazo procesal. Devuelve el detalle en vez de
     * solo escribirlo en el log, como el control de los vistos buenos, para
     * que el comando pueda enseñarlo y la API llegue a mostrarlo.
     *
     * Vigila los cuatro plazos que la migración documenta, no dos: antes el
     * término del período de prueba y el plazo del informe no se comprobaban
     * —de hecho `fecha_termino_prueba` no se llegaba a guardar—, así que un
     * expediente podía quedarse en prueba indefinidamente sin que nada lo
     * advirtiera.
     *
     * @return list<array{sumario_id:int, servidor_id:int, plazo:string, fecha_limite:string, dias_vencido:int, grave:bool}>
     */
    public function controlarPlazosLegales(): array
    {
        $hoy     = Carbon::today();
        $alertas = [];

        // 1. Notificación al sumariado: 3 días hábiles desde la apertura.
        $sinNotificar = Sumario::where('estado', EstadoSumario::ABIERTO)
            ->where('notificado_sn', false)
            ->get(['id', 'servidor_id', 'fecha_apertura']);

        foreach ($sinNotificar as $sumario) {
            $limite = $this->calcularDiasHabiles(
                Carbon::parse($sumario->fecha_apertura),
                self::DIAS_HABILES_NOTIFICACION
            );

            if ($hoy->gt($limite)) {
                $alertas[] = $this->alerta($sumario, 'notificacion', $limite, $hoy, false);
            }
        }

        // 2. Informe del instructor: 3 días hábiles desde el término de la
        //    prueba. Mientras el sumario siga en prueba, el informe no está.
        $enPrueba = Sumario::where('estado', EstadoSumario::EN_PRUEBA)
            ->whereNotNull('fecha_termino_prueba')
            ->get(['id', 'servidor_id', 'fecha_termino_prueba']);

        foreach ($enPrueba as $sumario) {
            $limite = $this->calcularDiasHabiles(
                Carbon::parse($sumario->fecha_termino_prueba),
                self::DIAS_HABILES_INFORME
            );

            if ($hoy->gt($limite)) {
                $alertas[] = $this->alerta($sumario, 'informe', $limite, $hoy, false);
            }
        }

        // 3. Resolución: 10 días hábiles desde el informe. Pasado el plazo, el
        //    sumario puede caducar y la sanción quedarse sin efecto.
        $conInforme = Sumario::where('estado', EstadoSumario::CON_INFORME)
            ->whereNotNull('fecha_informe')
            ->get(['id', 'servidor_id', 'fecha_informe']);

        foreach ($conInforme as $sumario) {
            $limite = $this->calcularDiasHabiles(
                Carbon::parse($sumario->fecha_informe),
                self::DIAS_HABILES_RESOLUCION
            );

            if ($hoy->gt($limite)) {
                $alertas[] = $this->alerta($sumario, 'resolucion', $limite, $hoy, true);
            }
        }

        foreach ($alertas as $alerta) {
            $mensaje = "Sumario #{$alerta['sumario_id']} excedió el plazo de {$alerta['plazo']} "
                ."(límite {$alerta['fecha_limite']}, {$alerta['dias_vencido']} día(s) de retraso).";

            $alerta['grave']
                ? Log::error("ALERTA LEGAL: {$mensaje} Riesgo de caducidad.")
                : Log::warning($mensaje);
        }

        return $alertas;
    }

    /** @return array{sumario_id:int, servidor_id:int, plazo:string, fecha_limite:string, dias_vencido:int, grave:bool} */
    private function alerta(
        Sumario $sumario,
        string $plazo,
        Carbon $limite,
        Carbon $hoy,
        bool $grave
    ): array {
        return [
            'sumario_id'   => $sumario->id,
            'servidor_id'  => $sumario->servidor_id,
            'plazo'        => $plazo,
            'fecha_limite' => $limite->toDateString(),
            'dias_vencido' => (int) $limite->diffInDays($hoy),
            'grave'        => $grave,
        ];
    }
}
