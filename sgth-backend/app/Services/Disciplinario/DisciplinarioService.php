<?php

namespace App\Services\Disciplinario;

use App\Contracts\Disciplinario\DisciplinarioServiceInterface;
use App\Enums\EstadoSumario;
use App\Enums\ProcedimientoDisciplinario;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoFalta;
use App\Enums\TipoNombramiento;
use App\Enums\TipoSancion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Disciplinario\FaltaReglamentoInterno;
use App\Models\Disciplinario\SancionDisciplinaria;
use App\Models\Disciplinario\Sumario;
use App\Models\Expediente\Servidor;
use App\Helpers\DiasHabilesHelper;
use App\Services\Expediente\MovimientoPersonalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    /**
     * Abre el sumario administrativo de un servidor de la LOSEP o, a un obrero,
     * el procedimiento del reglamento interno con la falta que se le imputa
     * (fase 4.4). Hasta aquí al obrero no se le abría nada: la pantalla lo
     * mandaba al visto bueno, y su multa —que la resolución sí admitía— no se
     * alcanzaba desde la aplicación.
     */
    public function abrirSumario(int $servidorId, array $datos, int $userId): Sumario
    {
        $servidor = Servidor::with('contratoVigente')->findOrFail($servidorId);
        $procedimiento = ProcedimientoDisciplinario::para($servidor->contratoVigente?->tipo_nombramiento);

        $falta = $procedimiento === ProcedimientoDisciplinario::REGLAMENTO_INTERNO
            ? $this->faltaDelCatalogo($datos['falta_reglamento_id'] ?? null)
            : null;

        $tieneAbierto = Sumario::where('servidor_id', $servidorId)
            ->whereNotIn('estado', [EstadoSumario::RESUELTO->value, EstadoSumario::CERRADO->value])
            ->exists();

        if ($tieneAbierto) {
            throw new ReglaNegocioException(
                $falta
                    ? 'El obrero ya tiene un procedimiento disciplinario en curso.'
                    : 'El servidor ya tiene un sumario administrativo en curso.'
            );
        }

        return Sumario::create([
            ...$datos,
            'procedimiento'       => $procedimiento,
            'falta_reglamento_id' => $falta?->id,
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

        // El obrero, con la falta de su reglamento interno: la que se le imputó
        // o, si la resolución la cambia, otra del catálogo vigente (fase 4.4).
        $falta = null;

        if ($sumario->esDelReglamentoInterno()) {
            $elegida = $datosSancion['falta_reglamento_id'] ?? null;

            $falta = ! $elegida || (int) $elegida === (int) $sumario->falta_reglamento_id
                ? $sumario->faltaReglamento
                : $this->faltaDelCatalogo($elegida);

            $this->assertSancionDelReglamento($falta, $datosSancion);
        } else {
            if (empty($datosSancion['tipo_falta'])) {
                throw ValidationException::withMessages([
                    'tipo_falta' => 'Indique la gravedad de la falta.',
                ]);
            }

            $this->assertSancionAplicableAlRegimen($sumario, $datosSancion['tipo_sancion']);
            $this->assertSancionAdmitidaPorLaFalta($datosSancion['tipo_falta'], $datosSancion['tipo_sancion']);
        }

        DB::beginTransaction();
        try {
            $sumario->estado = EstadoSumario::RESUELTO;
            $sumario->fecha_resolucion = now()->toDateString();
            $sumario->updated_by = $userId;
            $sumario->save();

            $sancion = SancionDisciplinaria::create([
                'sumario_id'       => $sumario->id,
                'falta_reglamento_id' => $falta?->id,
                // La gravedad de la LOSEP no le aplica al obrero.
                'tipo_falta'       => $falta ? null : $datosSancion['tipo_falta'],
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
                $cesacion = $this->movimientoPersonalService->registrar($sumario->servidor_id, [
                    'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
                    'subtipo_movimiento' => SubtipoMovimientoPersonal::DESTITUCION->value,
                    'descripcion'        => 'Destitución por sanción disciplinaria en Sumario Administrativo #' . $sumario->id,
                    'fecha_efectiva'     => $datosSancion['fecha_efectiva'] ?? now()->toDateString(),
                ]);
                $sancion->update(['movimiento_personal_id' => $cesacion->id]);
            }

            // La amonestación escrita también lleva acción [TH N13; fase 2.5],
            // además de la multa y la suspensión. La verbal no.
            if ($sancion->causalDeLaAccion()) {
                $this->crearAccionDeSancion($sumario, $sancion);
            }

            DB::commit();

            return $sumario;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * La sanción llega a su acción de personal con su causal (fase 2.5):
     * amonestación escrita, sanción pecuniaria o suspensión. Nace en BORRADOR,
     * como la cesación de una destitución. La multa y la suspensión, cuando
     * Talento Humano las registra, salen por correo al jefe de Gestión
     * Financiera con el descuento referencial (decisión de TH, 2026-10-04;
     * AvisoFinancieroSancionService). La suspensión lleva su período.
     *
     * Elegibles, por la causal: los nombramientos LOSEP que TH fijó el
     * 2026-09-28 y, para la multa y la amonestación escrita, los obreros
     * (2026-10-04 y fase 4.4), cuyo acto es la hoja de registro interno. La
     * suspensión no se le impone (assertSancionAplicableAlRegimen() y el
     * catálogo del reglamento interno). A un dignatario de elección popular la
     * sanción se le registra sin acción.
     */
    private function crearAccionDeSancion(Sumario $sumario, SancionDisciplinaria $sancion): void
    {
        $servidor     = Servidor::with('contratoVigente')->findOrFail($sumario->servidor_id);
        $nombramiento = $servidor->contratoVigente?->tipo_nombramiento;
        $causal       = $sancion->causalDeLaAccion();

        if (! $nombramiento || ! $causal?->elegiblePara($nombramiento)) {
            return;
        }

        $periodo = $sancion->tipo_sancion === TipoSancion::SUSPENSION
            ? $sancion->descuentoReferencial(null)
            : null;

        $accion = $this->movimientoPersonalService->registrar($servidor->id, [
            'tipo_movimiento'    => TipoMovimientoPersonal::REGIMEN_DISCIPLINARIO->value,
            'subtipo_movimiento' => $causal->value,
            'descripcion'        => $this->explicacionDeLaSancion($sumario, $sancion, $nombramiento),
            'fecha_efectiva'     => $sancion->fecha_efectiva->toDateString(),
            'fecha_inicio'       => $periodo['desde'] ?? null,
            'fecha_fin'          => $periodo['hasta'] ?? null,
        ]);

        $sancion->update(['movimiento_personal_id' => $accion->id]);
    }

    /**
     * El párrafo «Explicación» de la acción. Talento Humano lo puede editar en
     * borrador. A un obrero no lo rige la LOSEP sino el Código del Trabajo y
     * el reglamento interno, y el texto lo dice así.
     */
    private function explicacionDeLaSancion(
        Sumario $sumario,
        SancionDisciplinaria $sancion,
        TipoNombramiento $nombramiento,
    ): string {
        $reglamento = $sancion->faltaReglamento;

        if ($reglamento) {
            // La del obrero cita su reglamento interno (fase 4.4).
            $origen = "por la falta prevista en el {$reglamento->cita()} —"
                .Str::limit(rtrim(trim($reglamento->descripcion), '.'), 300)
                ."—, determinada en el procedimiento disciplinario N.º {$sumario->id}, conforme a los "
                .'Arts. 44 y 64 del Código del Trabajo.';
        } else {
            $falta  = mb_strtolower($sancion->tipo_falta->etiqueta());
            $base   = "por falta {$falta} determinada en el Sumario Administrativo N.º {$sumario->id}, ";
            $literal = $sancion->tipo_falta === TipoFalta::LEVE ? 'a' : 'b';
            $origen = $nombramiento === TipoNombramiento::CODIGO_TRABAJO
                ? $base.'conforme al Reglamento Interno de Trabajo y al Código del Trabajo, que no '
                    .'admite multas por más del 10 % de la remuneración.'
                : $base."conforme a los Arts. 42 literal {$literal}) y 43 de la Ley Orgánica de Servicio Público.";
        }

        if ($sancion->tipo_sancion === TipoSancion::AMONESTACION_ESCRITA) {
            return "Se impone la sanción de amonestación escrita {$origen}";
        }

        $efecto = $sancion->descuentoReferencial(null);

        if ($sancion->tipo_sancion === TipoSancion::MULTA) {
            return "Se impone la sanción de multa del {$efecto['detalle']}, {$origen}";
        }

        $desde = Carbon::parse($efecto['desde'])->format('d/m/Y');
        $hasta = Carbon::parse($efecto['hasta'])->format('d/m/Y');

        return "Se impone la sanción de suspensión temporal sin goce de remuneración por "
            ."{$efecto['detalle']}, del {$desde} al {$hasta}, {$origen}";
    }

    /**
     * La sanción tiene que ser de las que el Art. 42 de la LOSEP admite para
     * la gravedad de la falta (TipoFalta::sancionesAdmitidas()). El error va en
     * el campo de la sanción, para que la pantalla lo ponga bajo el selector.
     */
    private function assertSancionAdmitidaPorLaFalta(string $tipoFalta, string $tipoSancion): void
    {
        $falta   = TipoFalta::from($tipoFalta);
        $sancion = TipoSancion::from($tipoSancion);

        if ($falta->admite($sancion)) {
            return;
        }

        $admitidas = array_map(
            fn (TipoSancion $s) => mb_strtolower($s->etiqueta()),
            $falta->sancionesAdmitidas(),
        );
        $ultima = array_pop($admitidas);

        throw ValidationException::withMessages([
            'tipo_sancion' => sprintf(
                'Una falta %s se sanciona con %s o %s (Art. 42 de la LOSEP).',
                mb_strtolower($falta->etiqueta()),
                implode(', ', $admitidas),
                $ultima,
            ),
        ]);
    }

    /**
     * La falta del catálogo vigente del reglamento interno. Sin catálogo
     * cargado, al obrero no se le sanciona: el Código del Trabajo prohíbe
     * imponer lo que el reglamento interno no prevé (Art. 44 a).
     */
    private function faltaDelCatalogo(mixed $id): FaltaReglamentoInterno
    {
        if (! FaltaReglamentoInterno::activas()->exists()) {
            throw new ReglaNegocioException(
                'El reglamento interno todavía no tiene faltas cargadas, y sin ellas al obrero no se le sanciona '
                    .'(Código del Trabajo, Art. 44 a). Cárguelas en Disciplinario, «Reglamento interno».'
            );
        }

        $falta = $id ? FaltaReglamentoInterno::find((int) $id) : null;

        if (! $falta?->activo) {
            throw ValidationException::withMessages([
                'falta_reglamento_id' => $id
                    ? 'La falta no está en el catálogo vigente del reglamento interno.'
                    : 'Indique la falta del reglamento interno que se imputa.',
            ]);
        }

        return $falta;
    }

    /**
     * La sanción del obrero es una de las que su reglamento interno da a la
     * falta, y la multa no pasa del porcentaje que fija, que nunca supera el
     * 10 %. El error va en el campo, para que la pantalla lo ponga debajo.
     *
     * @param  array<string, mixed>  $datos
     */
    private function assertSancionDelReglamento(FaltaReglamentoInterno $falta, array $datos): void
    {
        $sancion = TipoSancion::from($datos['tipo_sancion']);

        if (! $falta->admite($sancion)) {
            $admitidas = implode(', ', array_map(
                fn (TipoSancion $s) => mb_strtolower($s->etiqueta()),
                $falta->sancionesAdmitidas(),
            ));

            throw ValidationException::withMessages([
                'tipo_sancion' => "El {$falta->articulo} del reglamento interno sanciona esta falta con: {$admitidas}.",
            ]);
        }

        if ($sancion === TipoSancion::MULTA
            && (float) ($datos['porcentaje_multa'] ?? 0) > (float) $falta->porcentaje_maximo_multa
        ) {
            throw ValidationException::withMessages([
                'porcentaje_multa' => "El {$falta->articulo} del reglamento interno admite una multa de hasta el "
                    .rtrim(rtrim(number_format((float) $falta->porcentaje_maximo_multa, 2, ',', '.'), '0'), ',').' %.',
            ]);
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
        if (! in_array($tipoSancion, [TipoSancion::DESTITUCION->value, TipoSancion::SUSPENSION->value], true)) {
            return;
        }

        $servidor = Servidor::with('contratoVigente')->findOrFail($sumario->servidor_id);
        $nombramiento = $servidor->contratoVigente?->tipo_nombramiento;

        if ($nombramiento !== TipoNombramiento::CODIGO_TRABAJO) {
            return;
        }

        if ($tipoSancion === TipoSancion::DESTITUCION->value) {
            throw new ReglaNegocioException(
                'Los obreros bajo Código del Trabajo no se destituyen por sumario administrativo. '
                    .'Tramite un visto bueno ante el Inspector del Trabajo desde el módulo Disciplinario.'
            );
        }

        // Decisión de TH (2026-10-04): a un obrero se le multa, pero no se le
        // suspende sin sueldo. El Código del Trabajo no regula la suspensión
        // como sanción, y su falta grave termina en visto bueno.
        throw new ReglaNegocioException(
            'A los obreros bajo Código del Trabajo no se les suspende: se les sanciona con '
                .'amonestación o multa. Una falta grave se tramita como visto bueno ante el '
                .'Inspector del Trabajo.'
        );
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
        // Solo los del sumario administrativo: los plazos del procedimiento
        // del reglamento interno los fija ese reglamento, que aún no tenemos
        // (fase 4.4). Sus fechas se guardan igual.
        $sinNotificar = Sumario::where('procedimiento', ProcedimientoDisciplinario::SUMARIO_ADMINISTRATIVO->value)
            ->where('estado', EstadoSumario::ABIERTO)
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
        $enPrueba = Sumario::where('procedimiento', ProcedimientoDisciplinario::SUMARIO_ADMINISTRATIVO->value)
            ->where('estado', EstadoSumario::EN_PRUEBA)
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
        $conInforme = Sumario::where('procedimiento', ProcedimientoDisciplinario::SUMARIO_ADMINISTRATIVO->value)
            ->where('estado', EstadoSumario::CON_INFORME)
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
