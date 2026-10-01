import type {
  CausalVistoBueno,
  EstadoSumario,
  EstadoVistoBueno,
  ServidorResumen,
  TipoFalta,
  TipoSancion,
} from '@/types/api'
import type { SemanticTone } from '@/config/design.tokens'

/**
 * Espeja `App\Enums\EstadoSumario::etiqueta()`. Mientras el API devuelva el
 * modelo crudo y no un recurso con `estado_label`, el texto vive en dos sitios
 * y `EnumsDisciplinarioTest` es la grapa que avisa si uno se mueve sin el otro.
 */
export const ESTADO_SUMARIO_LABELS: Record<EstadoSumario, string> = {
  abierto: 'Abierto',
  en_instruccion: 'En instrucción',
  en_prueba: 'En prueba',
  con_informe: 'Con informe',
  resuelto: 'Resuelto',
  apelado: 'Apelado',
  cerrado: 'Cerrado',
}

export const TONO_SUMARIO: Record<EstadoSumario, SemanticTone> = {
  abierto: 'info',
  en_instruccion: 'info',
  en_prueba: 'info',
  con_informe: 'info',
  resuelto: 'success',
  apelado: 'warning',
  cerrado: 'neutral',
}

/**
 * Espeja DisciplinarioService::TRANSICIONES_SUMARIO, el grafo que acepta
 * `PUT sumarios/{id}/avanzar`.
 *
 * 'resuelto' no figura como destino de nadie a propósito: se alcanza por
 * `POST sumarios/{id}/resolver`, que además impone la sanción.
 */
export const TRANSICIONES_SUMARIO: Record<EstadoSumario, EstadoSumario[]> = {
  abierto: ['en_instruccion', 'cerrado'],
  en_instruccion: ['en_prueba', 'cerrado'],
  en_prueba: ['con_informe', 'cerrado'],
  con_informe: ['cerrado'],
  resuelto: ['apelado', 'cerrado'],
  apelado: ['cerrado'],
  cerrado: [],
}

/**
 * El hito que sigue en la secuencia procesal, sin contar el cierre ni la
 * apelación, que son salidas y no avances. Sale del grafo de arriba para que
 * no haya dos listas que mantener de acuerdo.
 */
export function siguienteHito(estado: EstadoSumario): EstadoSumario | undefined {
  return TRANSICIONES_SUMARIO[estado]
    .find((destino) => destino !== 'cerrado' && destino !== 'apelado')
}

/**
 * El sumario se resuelve sobre el informe del instructor, que es su sustento:
 * de ahí que el plazo legal de resolución se cuente desde él y que
 * `controlarPlazosLegales()` solo vigile este estado. El backend admite
 * resolver desde cualquier estado no resuelto ni cerrado; la pantalla no lo
 * ofrece antes para no imponer una sanción sin instrucción previa. Un sumario
 * que termina sin sanción se cierra, no se resuelve.
 */
export function puedeResolverse(estado: EstadoSumario): boolean {
  return estado === 'con_informe'
}

/** Espeja `App\Enums\TipoFalta::etiqueta()`. */
export const TIPO_FALTA_LABELS: Record<TipoFalta, string> = {
  leve: 'Leve',
  grave: 'Grave',
  muy_grave: 'Muy grave',
}

/** Espeja `App\Enums\TipoSancion::etiqueta()`. */
export const TIPO_SANCION_LABELS: Record<TipoSancion, string> = {
  amonestacion_verbal: 'Amonestación verbal',
  amonestacion_escrita: 'Amonestación escrita',
  multa: 'Multa',
  suspension: 'Suspensión',
  destitucion: 'Destitución',
}

/** Espeja `App\Enums\EstadoVistoBueno::etiqueta()`. */
export const ESTADO_VISTO_BUENO_LABELS: Record<EstadoVistoBueno, string> = {
  solicitado: 'Solicitado',
  notificado: 'Notificado al trabajador',
  en_investigacion: 'En investigación',
  concedido: 'Concedido',
  negado: 'Negado',
  desistido: 'Desistido',
  impugnado: 'Impugnado',
}

export const TONO_VISTO_BUENO: Record<EstadoVistoBueno, SemanticTone> = {
  solicitado: 'info',
  notificado: 'info',
  en_investigacion: 'info',
  concedido: 'success',
  negado: 'danger',
  desistido: 'neutral',
  impugnado: 'warning',
}

/** Espeja VistoBuenoService::TRANSICIONES. */
export const TRANSICIONES_VISTO_BUENO: Record<EstadoVistoBueno, EstadoVistoBueno[]> = {
  solicitado: ['notificado', 'desistido'],
  notificado: ['en_investigacion', 'desistido'],
  en_investigacion: ['concedido', 'negado', 'desistido'],
  concedido: ['impugnado'],
  negado: ['impugnado'],
  desistido: [],
  impugnado: [],
}

/**
 * Espeja `App\Enums\CausalVistoBueno::etiqueta()`, al que tres de las siete se
 * le habían separado: faltaban «repetidas», «internos» y «contra el empleador»,
 * y la séptima se había quedado sin «prevención». El texto manda en el backend,
 * que es el que lo escribe en la descripción de la cesación.
 */
export const CAUSAL_LABELS: Record<CausalVistoBueno, string> = {
  faltas_puntualidad_asistencia: 'Faltas repetidas de puntualidad o asistencia, o abandono del trabajo',
  indisciplina_desobediencia: 'Indisciplina o desobediencia graves a los reglamentos internos',
  falta_probidad: 'Falta de probidad o conducta inmoral',
  injurias_graves: 'Injurias graves al empleador o su representante',
  ineptitud_manifiesta: 'Ineptitud manifiesta para la labor contratada',
  denuncia_injustificada_iess: 'Denuncia injustificada contra el empleador ante el Seguro Social',
  incumplimiento_seguridad: 'No acatar las medidas de seguridad, prevención e higiene',
}

/** Espeja `App\Enums\CausalVistoBueno::numeral()`. */
export const CAUSAL_NUMERAL: Record<CausalVistoBueno, number> = {
  faltas_puntualidad_asistencia: 1,
  indisciplina_desobediencia: 2,
  falta_probidad: 3,
  injurias_graves: 4,
  ineptitud_manifiesta: 5,
  denuncia_injustificada_iess: 6,
  incumplimiento_seguridad: 7,
}

/** Espeja `App\Enums\CausalVistoBueno::referenciaLegal()`. */
export function referenciaLegal(causal: CausalVistoBueno): string {
  return `Art. 172 núm. ${CAUSAL_NUMERAL[causal]} del Código del Trabajo`
}

export function nombreServidor(s?: ServidorResumen | null): string {
  if (!s) return '—'

  return [s.apellido, s.segundo_apellido, s.nombre, s.segundo_nombre]
    .filter(Boolean)
    .join(' ') || '—'
}

