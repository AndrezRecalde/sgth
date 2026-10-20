<?php

namespace App\Http\Resources\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\Permiso;
use App\Services\Expediente\MovimientoPersonalStateService;
use App\Services\Expediente\PosesionService;
use App\Services\Expediente\ProteccionMaternidad;
use App\Services\Expediente\TerminacionObreroService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El arreglo va enumerado campo por campo, no con `parent::toArray()`.
 *
 * Scramble solo infiere la forma de un recurso cuando `toArray()` devuelve un
 * arreglo literal; ante `parent::toArray()` emite `unknown[]` y el tipo llega
 * inservible al frontend. Si agregas una columna a `movimientos_personal` o
 * cargas una relación nueva en el controlador, agrégala también aquí: al
 * enumerar ya no hay volcado automático que las arrastre.
 *
 * @mixin \App\Models\Expediente\MovimientoPersonal
 */
class MovimientoPersonalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'servidor_id'        => $this->servidor_id,
            'tipo_movimiento'    => $this->tipo_movimiento,
            'subtipo_movimiento' => $this->subtipo_movimiento,

            // La clase legal y su nombre, que es lo que se muestra. La pantalla
            // no arma etiquetas propias.
            'clase'           => $this->clase,
            'familia'         => $this->clase?->familia(),
            'etiqueta'        => $this->etiqueta(),
            'causal'          => $this->causal(),
            // Una licencia registrada antes de la fase 2.2 no tiene causal: se
            // dice así en vez de dejar el hueco (diseño, 4.5).
            'causal_etiqueta' => $this->causal()?->etiqueta()
                ?? ($this->clase?->requiereCausal() ? 'No indicada (histórico)' : null),
            'causal_base_legal' => $this->causal()?->baseLegal(),
            // La terminación de una ocasional protegida por embarazo o
            // lactancia, mientras todavía se puede detener (fase 2.1).
            'aviso_proteccion' => $this->avisoDeProteccion(),

            // Lo que la pantalla decide con cada acción, respondido por el
            // backend. Antes `taxonomiaAccionPersonal.ts` lo copiaba a mano, y
            // cada regla nueva había que escribirla dos veces.
            'toca_el_vinculo'            => $this->tocaElVinculo(),
            'es_ausencia_temporal'       => $this->esAusenciaTemporal(),
            'propone_situacion'          => $this->proponeSituacion(),
            'tiene_efecto_economico'     => (bool) $this->tipo_movimiento?->tieneEfectoEconomico(),
            'tiene_documento_imprimible' => $this->tieneDocumentoImprimible(),
            // Con qué documento se formaliza (fase 2.7): el ingreso de un
            // ocasional es el registro de su contrato, sin acción.
            'instrumento'                => $this->instrumento,
            'instrumento_etiqueta'       => $this->instrumento?->etiqueta(),
            'editable_en_formulario'     => $this->editableEnFormulario(),

            // Lo que puede hacer con ella quien pregunta (fase 1.3): los pasos
            // del trámite para los que tiene permiso, y ninguno si la acción es
            // suya. La pantalla pone los botones según esto, no según el rol.
            'transiciones_permitidas' => $this->transicionesPermitidas($request),
            'puede_editar'            => $this->puedeEditar($request),
            'puede_anotar'            => $this->puedeAnotar($request),
            // La vista previa con la marca BORRADOR (fase 6.2).
            'puede_previsualizar'     => $this->puedePrevisualizar($request),

            'categoria'          => $this->categoria,
            'estado'             => $this->estado,
            'descripcion'        => $this->descripcion,
            // La base legal del documento (fase 6.1): la guardada —congelada
            // al suscribir— y la que pone su clase, para proponerla.
            'base_legal'             => $this->base_legal,
            'base_legal_de_la_clase' => $this->baseLegalDeLaClase(),
            // La casilla que marca en la rejilla del formulario del Ministerio.
            'casilla'                => $this->casilla()->etiqueta(),
            'observacion'        => $this->observacion,

            'fecha_efectiva' => $this->fecha_efectiva,
            'fecha_inicio'   => $this->fecha_inicio,
            'fecha_fin'      => $this->fecha_fin,

            // La posesión y el plazo para presentarse (Reglamento a la LOSEP,
            // Art. 20; fase 6.4): el término del borrador, hasta cuándo puede
            // presentarse —calculado al registrar— y la posesión registrada.
            'dias_para_presentarse'    => $this->dias_para_presentarse,
            'razon_dias_adicionales'   => $this->razon_dias_adicionales,
            'presentacion_vence_el'    => $this->presentacion_vence_el?->toDateString(),
            'presentacion_vencida'     => $this->presentacionVencida(),
            'fecha_posesion'           => $this->fecha_posesion?->toDateString(),
            'lugar_posesion'           => $this->lugar_posesion,
            'posesion_registrada_por'  => $this->posesion_registrada_por,
            'posesion_fuera_de_plazo'  => $this->posesionFueraDePlazo(),
            'admite_posesion'          => PosesionService::admitePosesion($this->resource),
            'puede_registrar_posesion' => $this->puedeRegistrarPosesion($request),

            'unidad_origen_id'  => $this->unidad_origen_id,
            'unidad_destino_id' => $this->unidad_destino_id,
            'institucion_destino'     => $this->institucion_destino,
            'para_estudios_o_eventos' => (bool) $this->para_estudios_o_eventos,
            // La licencia del Consejo: con o sin remuneración, y su prórroga (4.1b).
            'con_remuneracion'        => $this->con_remuneracion,
            'prorroga_causa'          => $this->prorroga_causa,
            'prorroga_causa_etiqueta' => $this->prorroga_causa?->etiqueta(),
            'puesto_origen_id'  => $this->puesto_origen_id,
            'puesto_destino_id' => $this->puesto_destino_id,
            // El grupo y la denominación con que se firmó (fase 7.1b): una
            // revisión de la clasificación posterior no los cambia.
            'grupo_ocupacional_origen_id'  => $this->grupo_ocupacional_origen_id,
            'cargo_origen_id'              => $this->cargo_origen_id,
            'grupo_ocupacional_destino_id' => $this->grupo_ocupacional_destino_id,
            'cargo_destino_id'             => $this->cargo_destino_id,

            // Datos del vínculo que Talento Humano fija en borrador y que se
            // materializan en el ContratoServidor al registrar la acción.
            'tipo_nombramiento_propuesto' => $this->tipo_nombramiento_propuesto,
            // Para qué se da el provisional, y el acta del de prueba (fase 3.2).
            'supuesto_provisional_propuesto' => $this->supuesto_provisional_propuesto,
            'supuesto_provisional_etiqueta'  => $this->supuesto_provisional_propuesto?->etiqueta(),
            'acta_concurso_numero'        => $this->acta_concurso_numero,
            'acta_concurso_fecha'         => $this->acta_concurso_fecha,
            // La credencial del CNE y el acta de posesión de una autoridad (fase 4.1).
            'credencial_cne_numero'       => $this->credencial_cne_numero,
            'credencial_cne_fecha'        => $this->credencial_cne_fecha,
            'acta_posesion_numero'        => $this->acta_posesion_numero,
            'remuneracion_propuesta'      => $this->remuneracion_propuesta,
            'numero_contrato'             => $this->numero_contrato,
            'partida_presupuestaria_id'   => $this->partida_presupuestaria_id,
            'puede_marcar'                => $this->puede_marcar,
            'requiere_dictamen_medico'    => $this->requiere_dictamen_medico,
            'fecha_fin_propuesta'         => $this->fecha_fin_propuesta,
            // El plazo que tenía el contrato civil antes de la adenda (fase 4.2).
            'fecha_fin_origen'            => $this->fecha_fin_origen,

            // Situación actual congelada al crear la acción: no se deriva del
            // puesto de origen, que pudo cambiar de escala o de partida desde
            // entonces.
            'remuneracion_origen' => $this->remuneracion_origen,
            'partida_origen_id'   => $this->partida_origen_id,

            'movimiento_previo_id' => $this->movimiento_previo_id,
            /** Ausencia temporal que este ingreso viene a cubrir. */
            'cubre_movimiento_id'  => $this->cubre_movimiento_id,
            /**
             * El acto con que se enlaza (fase 2.4): la ausencia que cierra un
             * reintegro, o el reintegro del que salió la cesación de un
             * reemplazo.
             */
            'movimiento_relacionado_id' => $this->movimiento_relacionado_id,
            'relacionado'               => $this->relacionado(),
            // La de una cesación que una sentencia dejó sin efecto (fase 2.6).
            'restituida_por'            => $this->restituidaPor(),

            'codigo'                      => $this->codigo,
            'codigo_registro'             => $this->codigo_registro,
            'fecha_registro'              => $this->fecha_registro,
            // Registrada pero sin efecto todavía, porque rige más tarde (fase
            // 1.6): el vínculo no cambia hasta su fecha.
            'efecto_aplicado_en'          => $this->efecto_aplicado_en,
            'pendiente_de_vigencia'       => $this->pendienteDeVigencia(),
            'resolucion_numero'           => $this->resolucion_numero,
            // La solicitud del portal que atiende (fase 6.3b).
            'solicitud_folio'             => $this->solicitud?->folio,
            // El decreto, acuerdo o resolución que la origina (fase 6.1).
            'acto_origen_tipo'            => $this->acto_origen_tipo,
            'acto_origen_numero'          => $this->acto_origen_numero,
            'acto_origen_fecha'           => $this->acto_origen_fecha?->toDateString(),
            // El informe de la UATH del traslado (fase 3.1).
            'informe_uath_numero'         => $this->informe_uath_numero,
            // La sentencia de una restitución (fase 2.6).
            'causa_judicial_numero'       => $this->causa_judicial_numero,
            'unidad_judicial'             => $this->unidad_judicial,
            'fecha_sentencia'             => $this->fecha_sentencia,
            'fecha_ejecutoria'            => $this->fecha_ejecutoria,
            // O la providencia que ordena el reintegro del obrero (fase 4.5b).
            'fecha_providencia'           => $this->fecha_providencia,
            // El lote del que nació: el incremento de los obreros (fase 4.6).
            'lote_id'                     => $this->lote_id,
            'remuneraciones_dejadas_de_percibir' => $this->remuneraciones_dejadas_de_percibir,
            'documento_respaldo'          => $this->documento_respaldo,
            'dictamen_presupuestario_ref' => $this->dictamen_presupuestario_ref,

            // Firmantes sellados al suscribir: se copian dentro de la acción
            // para que una reimpresión no atribuya la firma a quien ocupe hoy
            // el cargo.
            'fecha_suscripcion'         => $this->fecha_suscripcion,
            'firmante_autoridad_id'     => $this->firmante_autoridad_id,
            'firmante_autoridad_nombre' => $this->firmante_autoridad_nombre,
            'firmante_autoridad_cargo'  => $this->firmante_autoridad_cargo,
            'firmante_autoridad_cedula' => $this->firmante_autoridad_cedula,
            'firmante_th_id'            => $this->firmante_th_id,
            'firmante_th_nombre'        => $this->firmante_th_nombre,
            'firmante_th_cargo'         => $this->firmante_th_cargo,
            'firmante_th_cedula'        => $this->firmante_th_cedula,

            'lugar_trabajo'  => $this->lugar_trabajo,
            // El lugar de antes del cambio y el consentimiento del cambio de
            // ocupación de un obrero (fase 4.3).
            'lugar_trabajo_origen' => $this->lugar_trabajo_origen,
            'fecha_consentimiento' => $this->fecha_consentimiento,
            // La terminación de un obrero (fase 4.5): cuándo presentó la
            // renuncia, si el desahucio rige antes de los 15 días de aviso, lo
            // que le corresponde según el Código —sin montos, desde el
            // 2026-10-20— y el acta de finiquito.
            'fecha_presentacion_renuncia' => $this->fecha_presentacion_renuncia,
            'desahucio_sin_el_aviso'      => TerminacionObreroService::desahucioSinElAviso($this->resource),
            'lo_que_corresponde'          => app(TerminacionObreroService::class)->loQueCorresponde($this->resource),
            'acta_finiquito'              => $this->whenLoaded('actaFiniquito'),
            'caucionado'     => $this->caucionado,
            'caucion_numero' => $this->caucion_numero,
            'caucion_fecha'  => $this->caucion_fecha,

            // Al enumerar, la relación cargada ya no pisa el FK. Antes sí:
            // `parent::toArray()` snake-caseaba `autorizadoPor` a
            // `autorizado_por` y sustituía el id por el User completo, con el
            // servidor anidado y sus datos personales.
            'autorizado_por'     => $this->autorizado_por,
            'notificado_por'     => $this->notificado_por,
            'fecha_notificacion' => $this->fecha_notificacion,
            // Cómo se notificó, el acuse del servidor y el correo (fase 6.3a).
            'notificacion_medio'          => $this->notificacion_medio,
            'notificacion_resultado'      => $this->notificacion_resultado,
            'notificacion_razon'          => $this->notificacion_razon,
            'notificacion_testigo_nombre' => $this->notificacion_testigo_nombre,
            'notificacion_testigo_cedula' => $this->notificacion_testigo_cedula,
            'acuse_en'                    => $this->acuse_en?->toIso8601String(),
            'correo_enviado_en'           => $this->correo_enviado_en?->toIso8601String(),
            'correo_destino'              => $this->correo_destino,
            'motivo_anulacion'   => $this->motivo_anulacion,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // ── Relaciones, solo si fueron cargadas ─────────────────
            'servidor'       => $this->whenLoaded('servidor'),
            'unidad_origen'  => $this->whenLoaded('unidadOrigen'),
            'unidad_destino' => $this->whenLoaded('unidadDestino'),
            'puesto_origen'  => $this->whenLoaded('puestoOrigen'),
            // `rmu` es un accesor del puesto —sale de su grupo ocupacional— y
            // no viaja en toArray(). El cierre del vínculo lo necesita para
            // sugerir la remuneración de la escala en los ingresos LOSEP.
            'puesto_destino' => $this->whenLoaded('puestoDestino', fn () => [
                ...$this->puestoDestino->toArray(),
                'rmu' => $this->puestoDestino->rmu,
            ]),
            'partida_origen'          => $this->whenLoaded('partidaOrigen'),
            'partida_presupuestaria'  => $this->whenLoaded('partidaPresupuestaria'),
            'solicitud_certificacion' => $this->whenLoaded('solicitudCertificacion'),
            'movimiento_previo'       => $this->whenLoaded('movimientoPrevio'),
            // La ausencia que este ingreso cubre, con el titular ausente: es
            // lo que hace legible "reemplaza a X" sin una consulta extra.
            'cubre_movimiento'        => $this->whenLoaded('cubreMovimiento'),
            // Lo que se le anotó después de emitida (fase 1.4).
            'anotaciones'             => AnotacionAccionPersonalResource::collection(
                $this->whenLoaded('anotaciones')
            ),

            'autorizado_por_usuario' => $this->whenLoaded(
                'autorizadoPor',
                fn () => [
                    'id'              => $this->autorizadoPor->id,
                    'nombre_completo' => $this->autorizadoPor->nombre_completo,
                ]
            ),
        ];
    }

    /**
     * Lo justo para decir «cierra la AP-… (Comisión de servicios…)» sin otra
     * consulta desde la pantalla.
     *
     * @return array{id: int, clase: ?string, codigo_registro: ?string, etiqueta: ?string, fecha_efectiva: ?string, fecha_inicio: ?string, fecha_fin: ?string}|null
     */
    private function relacionado(): ?array
    {
        if (! $this->movimiento_relacionado_id) {
            return null;
        }

        $otro = $this->movimientoRelacionado;

        return $otro ? [
            'id'              => $otro->id,
            'clase'           => $otro->clase?->value,
            'codigo_registro' => $otro->codigo_registro,
            // La causal cuando la hay: «Destitución» dice más que «Cesación».
            'etiqueta'        => $otro->etiquetaAusencia() ?? $otro->causal()?->etiqueta() ?? $otro->etiqueta(),
            'fecha_efectiva'  => $otro->fecha_efectiva?->toDateString(),
            'fecha_inicio'    => $otro->fecha_inicio?->toDateString(),
            'fecha_fin'       => $otro->fecha_fin?->toDateString(),
        ] : null;
    }

    /**
     * La restitución que deja sin efecto esta cesación, si la hay: en el
     * obrero, su reintegro por despido ineficaz (fase 4.5b).
     *
     * @return array{id: int, codigo_registro: ?string, estado: string, etiqueta: string}|null
     */
    private function restituidaPor(): ?array
    {
        if (! $this->cierraElVinculo()) {
            return null;
        }

        $restitucion = $this->resource->restitucion;

        return $restitucion ? [
            'id'              => $restitucion->id,
            'codigo_registro' => $restitucion->codigo_registro,
            'estado'          => $restitucion->estado->value,
            'etiqueta'        => $restitucion->etiqueta(),
        ] : null;
    }

    private function avisoDeProteccion(): ?string
    {
        $porVenir = in_array($this->estado, [EstadoAccionPersonal::BORRADOR, EstadoAccionPersonal::SUSCRITA], true);

        return $porVenir
            && $this->causal()?->protegeEmbarazoYLactancia()
            && ProteccionMaternidad::consta($this->servidor_id)
                ? ProteccionMaternidad::aviso()
                : null;
    }

    /**
     * En un método propio y no en línea: Scramble no sigue el tipo a través de
     * `app()`, y sin esto la lista llegaba al frontend como `string`.
     *
     * @return list<EstadoAccionPersonal>
     */
    private function transicionesPermitidas(Request $request): array
    {
        return app(MovimientoPersonalStateService::class)
            ->destinosPara($this->resource, $request->user());
    }

    /**
     * Anotar es lo que queda en lugar de corregir lo ya suscrito: lo hace quien
     * prepara acciones, y no en las propias.
     */
    private function puedeAnotar(Request $request): bool
    {
        $usuario = $request->user();

        return $usuario !== null
            && $this->estado !== null
            && $this->estado !== EstadoAccionPersonal::BORRADOR
            && ! $this->resource->esSobre($usuario)
            && $usuario->can(Permiso::PREPARAR_ACCION_PERSONAL->value);
    }

    /**
     * La vista previa con la marca BORRADOR (fase 6.2; TH 27): de lo que
     * todavía no se registra y tiene documento, para quien prepara acciones y
     * nunca sobre sí mismo. El titular no ve sus borradores (diseño, 6.3).
     */
    private function puedePrevisualizar(Request $request): bool
    {
        $usuario = $request->user();

        return $usuario !== null
            && in_array($this->estado, [EstadoAccionPersonal::BORRADOR, EstadoAccionPersonal::SUSCRITA], true)
            && $this->tieneDocumentoImprimible()
            && ! $this->resource->esRegistroDeContrato()
            && ! $this->resource->esSobre($usuario)
            && $usuario->can(Permiso::PREPARAR_ACCION_PERSONAL->value);
    }

    /**
     * La posesión la registra quien notifica (fase 6.4): una vez, con la acción
     * registrada y ya vigente, y nunca sobre sí mismo.
     */
    private function puedeRegistrarPosesion(Request $request): bool
    {
        $usuario = $request->user();

        return $usuario !== null
            && PosesionService::admitePosesion($this->resource)
            && in_array($this->estado, [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA], true)
            && $this->fecha_posesion === null
            && $this->fecha_efectiva !== null
            && ! $this->fecha_efectiva->isFuture()
            && ! $this->resource->esSobre($usuario)
            && $usuario->can(Permiso::NOTIFICAR_ACCION_PERSONAL->value);
    }

    /**
     * Corregir un borrador es prepararlo: hace falta el permiso, que la acción
     * se corrija con el formulario y no en su propia pantalla, y que no sea
     * del mismo usuario.
     */
    private function puedeEditar(Request $request): bool
    {
        $usuario = $request->user();

        return $usuario !== null
            && $this->estado === EstadoAccionPersonal::BORRADOR
            && $this->editableEnFormulario()
            && ! $this->resource->esSobre($usuario)
            && $usuario->can(Permiso::PREPARAR_ACCION_PERSONAL->value);
    }
}
