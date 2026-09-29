/**
 * Espejo de la taxonomía de dos niveles del backend
 * (TipoMovimientoPersonal + SubtipoMovimientoPersonal). El backend sigue
 * siendo la autoridad: esto existe para no ofrecer en el formulario opciones
 * que de todas formas serían rechazadas, y para etiquetarlas igual.
 */

import type { SubtipoMovimientoPersonal, TipoMovimientoPersonal } from '@/types/api'

/**
 * Los tipos que el formulario ofrece: un subconjunto del enum del backend, no
 * todo él. Los históricos genéricos —novedad de contrato, cambio de puesto— y
 * los planos legados —traslado, traspaso— aparecen en el historial pero no se
 * crean desde aquí.
 *
 * Es una tupla y no una unión escrita a mano porque hace tres trabajos con una
 * sola lista: `satisfies` comprueba que cada miembro exista de verdad en el enum
 * del backend —si retira uno, como pasó con 'ascenso' el 2026-07-23, esto deja
 * de compilar en vez de seguir ofreciendo una opción que el servidor rechaza—,
 * de ella sale el tipo `AccionTipo`, y de ella sale el `z.enum()` del esquema,
 * que antes repetía los ocho valores en otro archivo.
 */
export const TIPOS_DEL_FORMULARIO = [
  'ingreso',
  'cambio_administrativo',
  'cesacion_funciones',
  'regimen_disciplinario',
  'cambio_denominacion',
  'prestacion_servicios',
  'licencia_sin_remuneracion',
  'incremento_remuneracion',
] as const satisfies readonly TipoMovimientoPersonal[]

export type AccionTipo = (typeof TIPOS_DEL_FORMULARIO)[number]

/**
 * Los subtipos sí son todos los del backend, así que el tipo se aliasa y la
 * tupla existe solo para el `z.enum()`. Que esté completa lo comprueba
 * `SUBTIPO_LABELS`, que es `Record<AccionSubtipo, string>` y no compila si falta
 * una etiqueta.
 */
export const SUBTIPOS_DE_ACCION = [
  'traslado_administrativo',
  'traspaso',
  'comision_con_remuneracion',
  'comision_sin_remuneracion',
  'sancion_disciplinaria',
  'renuncia',
  'destitucion',
  'jubilacion',
  'incapacidad',
  'contrato_finalizado',
  'visto_bueno',
] as const satisfies readonly SubtipoMovimientoPersonal[]

export type AccionSubtipo = SubtipoMovimientoPersonal

export const TIPO_LABELS: Record<AccionTipo, string> = {
  ingreso: 'Ingreso y Vinculación',
  cambio_administrativo: 'Cambio Administrativo',
  cesacion_funciones: 'Cesación de Funciones',
  regimen_disciplinario: 'Régimen Disciplinario',
  cambio_denominacion: 'Cambio de Denominación',
  prestacion_servicios: 'Prestación de Servicios',
  licencia_sin_remuneracion: 'Licencia sin Remuneración',
  incremento_remuneracion: 'Incremento de Remuneración',
}

/**
 * Tipos que aparecen en el historial pero que no se crean desde el formulario
 * de Acción de Personal: la subrogación nace en su propia pantalla, y el resto
 * son bitácora interna del expediente o tipos planos heredados. Van aparte de
 * TIPO_LABELS a propósito — necesitan etiqueta para *mostrarse*, no para
 * ofrecerse como opción.
 *
 * El tipo del mapa es `Exclude<…, AccionTipo>`, no `Record<string, string>`:
 * juntos, los dos mapas tienen que cubrir el enum entero, y así lo comprueba
 * tsc. Con `string` como clave faltaba 'comision_sin_remuneracion' —tipo plano
 * legado, de cuando la comisión no era todavía subtipo de Cambio
 * Administrativo— y `etiquetaTipoMovimiento()` caía en su último `??`: la tabla
 * del historial imprimía el slug crudo «comision_sin_remuneracion».
 */
const TIPO_LABELS_FUERA_DEL_FORMULARIO:
  Record<Exclude<TipoMovimientoPersonal, AccionTipo>, string> = {
    subrogacion:               'Subrogación',
    novedad_contrato:          'Novedad de Contrato',
    cambio_puesto:             'Cambio de Puesto',
    cambio_regimen:            'Cambio de Régimen',
    traslado:                  'Traslado',
    traspaso:                  'Traspaso',
    comision_servicios:        'Comisión de Servicios',
    comision_sin_remuneracion: 'Comisión de Servicios sin Remuneración',
    egreso:                    'Egreso',
    destitucion:               'Destitución',
  }

/**
 * Espeja TipoMovimientoPersonal::tieneEfectoEconomico(). Estos comprometen
 * presupuesto (Art. 105 LOSEP) y el backend rechaza suscribirlos sin la
 * referencia del dictamen presupuestario, así que hay que pedirla antes de
 * intentar la transición en vez de dejar que falle.
 */
export function tieneEfectoEconomico(tipo?: TipoMovimientoPersonal | null): boolean {
  return tipo === 'subrogacion' || tipo === 'incremento_remuneracion'
}

/**
 * Etiqueta legible de cualquier tipo de movimiento, venga o no del formulario.
 *
 * Los dos mapas se juntan en uno exhaustivo, así que no hace falta ni asertar
 * la clave ni caer al slug cuando no hay etiqueta: el tipo del `Record` obliga a
 * que estén las dieciocho.
 */
const ETIQUETAS_POR_TIPO: Record<TipoMovimientoPersonal, string> = {
  ...TIPO_LABELS,
  ...TIPO_LABELS_FUERA_DEL_FORMULARIO,
}

export function etiquetaTipoMovimiento(tipo?: TipoMovimientoPersonal | null): string {
  return tipo ? ETIQUETAS_POR_TIPO[tipo] : '—'
}

export const SUBTIPO_LABELS: Record<AccionSubtipo, string> = {
  traslado_administrativo: 'Traslado Administrativo',
  traspaso: 'Traspaso',
  comision_con_remuneracion: 'Comisión de Servicios con Remuneración',
  comision_sin_remuneracion: 'Comisión de Servicios sin Remuneración',
  sancion_disciplinaria: 'Sanción Disciplinaria',
  renuncia: 'Renuncia',
  destitucion: 'Destitución',
  jubilacion: 'Jubilación',
  incapacidad: 'Incapacidad',
  contrato_finalizado: 'Contrato Finalizado',
  visto_bueno: 'Visto Bueno',
}

/** Espeja TipoMovimientoPersonal::subtiposPermitidos(). */
export const SUBTIPOS_POR_TIPO: Partial<Record<AccionTipo, AccionSubtipo[]>> = {
  cambio_administrativo: [
    'traslado_administrativo',
    'traspaso',
    'comision_con_remuneracion',
    'comision_sin_remuneracion',
  ],
  regimen_disciplinario: ['sancion_disciplinaria'],
  cesacion_funciones: [
    'renuncia',
    'destitucion',
    'jubilacion',
    'incapacidad',
    'contrato_finalizado',
    'visto_bueno',
  ],
}

const CARRERA = ['nombramiento_permanente', 'nombramiento_provisional', 'servicios_ocasionales']

/** Espeja SubtipoMovimientoPersonal::nombramientosElegibles(). */
const NOMBRAMIENTOS_POR_SUBTIPO: Record<AccionSubtipo, string[]> = {
  traslado_administrativo: ['nombramiento_permanente'],
  traspaso: ['nombramiento_permanente'],
  comision_con_remuneracion: ['nombramiento_permanente'],
  comision_sin_remuneracion: ['nombramiento_permanente'],
  sancion_disciplinaria: CARRERA,
  renuncia: CARRERA,
  destitucion: CARRERA,
  jubilacion: CARRERA,
  incapacidad: CARRERA,
  contrato_finalizado: ['servicios_profesionales'],
  visto_bueno: ['codigo_trabajo'],
}

/** Espeja TipoMovimientoPersonal::elegiblePara() para los tipos sin subtipo. */
const NOMBRAMIENTOS_POR_TIPO_SIMPLE: Partial<Record<AccionTipo, string[]>> = {
  cambio_denominacion: ['codigo_trabajo'],
  // Las tres que nombró TH (2026-09-28). Antes espejaba un
  // `esLosep() && !== permanente` que daba un conjunto parecido y no el mismo:
  // dejaba fuera Servicios Profesionales y colaba Libre Nombramiento y Elección
  // Popular.
  prestacion_servicios: [
    'nombramiento_provisional',
    'servicios_ocasionales',
    'servicios_profesionales',
  ],
  licencia_sin_remuneracion: [
    'nombramiento_permanente',
    'codigo_trabajo',
    'eleccion_popular',
  ],
  // Solo obreros (TH, 2026-09-28). Se ofrecía a todos, porque se añadía a la
  // lista fuera de este mapa y sin condición.
  incremento_remuneracion: ['codigo_trabajo'],
}

export function subtiposElegibles(
  tipo: AccionTipo,
  tipoNombramiento?: string | null,
): AccionSubtipo[] {
  const subtipos = SUBTIPOS_POR_TIPO[tipo] ?? []

  if (!tipoNombramiento) return subtipos

  return subtipos.filter((s) =>
    NOMBRAMIENTOS_POR_SUBTIPO[s].includes(tipoNombramiento),
  )
}

/**
 * Tipos ofrecibles para el nombramiento vigente del servidor. 'ingreso' se
 * excluye: no se registra desde el expediente de alguien ya vinculado — nace
 * del reclutamiento o del formulario de Ingreso y Vinculación.
 */
export function tiposElegibles(tipoNombramiento?: string | null): AccionTipo[] {
  if (!tipoNombramiento) return []

  const conSubtipos = (Object.keys(SUBTIPOS_POR_TIPO) as AccionTipo[])
    .filter((t) => subtiposElegibles(t, tipoNombramiento).length > 0)

  const simples = (Object.keys(NOMBRAMIENTOS_POR_TIPO_SIMPLE) as AccionTipo[])
    .filter((t) => NOMBRAMIENTOS_POR_TIPO_SIMPLE[t]!.includes(tipoNombramiento))

  return [...conSubtipos, ...simples]
}

export function requiereSubtipo(tipo: AccionTipo): boolean {
  return (SUBTIPOS_POR_TIPO[tipo] ?? []).length > 0
}

/**
 * ¿El formulario de acción de personal puede capturar y corregir este tipo?
 *
 * No todo borrador es de un tipo que el formulario represente: los planos
 * legados —traslado, traspaso, comision_servicios, destitucion— también nacen en
 * borrador (`modificaVinculo()` en el backend), y la subrogación tiene su propia
 * pantalla. Abrirlos en el formulario dejaba un `tipo_movimiento` que el esquema
 * Zod rechaza, y el envío moría en silencio.
 *
 * Es un predicado de tipo, no una aserción: ensancha la tupla hacia la unión del
 * enum para comparar, que es ensanchar hacia la verdad.
 */
export function esTipoDelFormulario(
  tipo?: TipoMovimientoPersonal | null,
): tipo is AccionTipo {
  return !!tipo && (TIPOS_DEL_FORMULARIO as readonly TipoMovimientoPersonal[]).includes(tipo)
}

/**
 * Acciones que reubican al servidor: el formulario muestra la comparación
 * "situación actual vs propuesta" y pide unidad, puesto, RMU y partida.
 *
 * El traspaso y la prestación de servicios. Son la misma figura repartida por
 * tipo de nombramiento: TH (2026-09-28) describió la prestación de servicios
 * como «prácticamente como un traspaso pero se hace para servidores con
 * nombramientos Provisionales, Ocasionales, Servicios Profesionales, igualmente
 * con su situación actual y situación propuesta», y el traspaso solo aplica a
 * permanentes. La prestación de servicios entró aquí ese día: se guardaba sin
 * puesto propuesto y el registro reubicaba a nadie.
 *
 * El traslado administrativo salió el mismo día: TH aclaró que es el
 * intercambio de personal ENTRE INSTITUCIONES, no un movimiento interno, así
 * que no tiene puesto de destino dentro del GAD que proponer — el formulario le
 * pedía una unidad y un puesto del organigrama propio que para esa figura no
 * significan nada.
 *
 * Espeja `MovimientoPersonal::reubicaAlServidor()`, donde está el detalle.
 */
export function reubicaAlServidor(
  tipo?: TipoMovimientoPersonal | null,
  subtipo?: AccionSubtipo | null,
): boolean {
  return tipo === 'prestacion_servicios' || subtipo === 'traspaso'
}

export function esComision(subtipo?: AccionSubtipo | null): boolean {
  return subtipo === 'comision_con_remuneracion'
    || subtipo === 'comision_sin_remuneracion'
}

/**
 * ¿Esta acción propone una situación nueva?
 *
 * El ingreso —que crea el vínculo—, las que reubican al servidor, y la
 * subrogación: aunque el vínculo original se conserva, el servidor pasa a
 * ocupar otro puesto y a cobrar por él, que es justamente lo que la autoridad
 * autoriza. Una cesación termina el vínculo y no propone nada; una comisión,
 * una licencia o una sanción dejan al servidor en su mismo puesto. Mostrarles
 * una columna de "situación propuesta" en blanco hace creer que falta llenar
 * algo.
 */
export function proponeSituacion(
  tipo?: TipoMovimientoPersonal | null,
  subtipo?: AccionSubtipo | null,
): boolean {
  return tipo === 'ingreso' || tipo === 'subrogacion' || reubicaAlServidor(tipo, subtipo)
}

/**
 * Espeja SubtipoMovimientoPersonal::cierraVinculo(): las cesaciones terminan la
 * relación laboral al registrarse.
 */
const CIERRAN_EL_VINCULO: AccionSubtipo[] = [
  'renuncia', 'destitucion', 'jubilacion', 'incapacidad', 'contrato_finalizado',
  'visto_bueno',
]

/**
 * ¿Registrar esta acción cambia algo en el vínculo del servidor?
 *
 * Espeja `MovimientoPersonal::tocaElVinculo()`: crea el vínculo, reubica dentro
 * de él, o lo cierra. El resto —comisiones, licencias, sanciones, cambios de
 * denominación, incrementos— se registra sin tocarlo.
 *
 * Aquí sirve para una sola cosa: avisar, antes de anular algo ya registrado, de
 * que se va a deshacer su efecto y el servidor volverá a su situación anterior.
 *
 * Los dos tipos planos legados se nombran a mano porque el backend los resuelve
 * con `subtipoEquivalente()` y aquí no hay ese mapa: no se crean desde el
 * formulario, pero sí aparecen en el historial de expedientes migrados, y sin
 * ellos el aviso callaría justo donde más falta hace.
 */
export function tocaElVinculo(movimiento: {
  tipo_movimiento?: TipoMovimientoPersonal | null
  subtipo_movimiento?: AccionSubtipo | null
}): boolean {
  const { tipo_movimiento: tipo, subtipo_movimiento: subtipo } = movimiento

  return tipo === 'ingreso'
    || tipo === 'traspaso'
    || tipo === 'destitucion'
    || reubicaAlServidor(tipo, subtipo)
    || (!!subtipo && CIERRAN_EL_VINCULO.includes(subtipo))
}

/** Acciones que apartan temporalmente al servidor: lo suyo es el período. */
export function esAusenciaTemporal(
  tipo?: TipoMovimientoPersonal | null,
  subtipo?: AccionSubtipo | null,
): boolean {
  return tipo === 'licencia_sin_remuneracion' || esComision(subtipo)
}

/** Cesaciones cuyo dictamen médico viene pre-marcado desde el backend. */
export function requiereDictamenPorDefecto(subtipo?: AccionSubtipo | null): boolean {
  return subtipo === 'jubilacion' || subtipo === 'incapacidad'
}
