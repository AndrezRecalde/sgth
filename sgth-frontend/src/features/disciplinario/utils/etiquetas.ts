import type {
  CausalVistoBueno,
  EstadoSumario,
  EstadoVistoBueno,
  ServidorResumen,
  TipoSancion,
} from '@/types/api'

export const ESTADO_SUMARIO_LABELS: Record<EstadoSumario, string> = {
  abierto: 'Abierto',
  en_instruccion: 'En instrucción',
  en_prueba: 'En prueba',
  con_informe: 'Con informe',
  resuelto: 'Resuelto',
  apelado: 'Apelado',
  cerrado: 'Cerrado',
}

export const ESTADO_SUMARIO_COLORS: Record<EstadoSumario, string> = {
  abierto: 'blue',
  en_instruccion: 'cyan',
  en_prueba: 'indigo',
  con_informe: 'violet',
  resuelto: 'emerald',
  apelado: 'orange',
  cerrado: 'gray',
}

/**
 * Siguiente hito procesal del sumario. Refleja el grafo de
 * DisciplinarioService::TRANSICIONES_SUMARIO — 'resuelto' se omite a
 * propósito: se alcanza por el endpoint de resolución, que aplica la sanción.
 */
export const SIGUIENTE_HITO_SUMARIO: Partial<Record<EstadoSumario, EstadoSumario>> = {
  abierto: 'en_instruccion',
  en_instruccion: 'en_prueba',
  en_prueba: 'con_informe',
}

export const TIPO_SANCION_LABELS: Record<TipoSancion, string> = {
  amonestacion_verbal: 'Amonestación verbal',
  amonestacion_escrita: 'Amonestación escrita',
  multa: 'Multa',
  suspension: 'Suspensión',
  destitucion: 'Destitución',
}

export const ESTADO_VISTO_BUENO_LABELS: Record<EstadoVistoBueno, string> = {
  solicitado: 'Solicitado',
  notificado: 'Notificado al trabajador',
  en_investigacion: 'En investigación',
  concedido: 'Concedido',
  negado: 'Negado',
  desistido: 'Desistido',
  impugnado: 'Impugnado',
}

export const ESTADO_VISTO_BUENO_COLORS: Record<EstadoVistoBueno, string> = {
  solicitado: 'blue',
  notificado: 'cyan',
  en_investigacion: 'indigo',
  concedido: 'emerald',
  negado: 'red',
  desistido: 'gray',
  impugnado: 'orange',
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

export const CAUSAL_LABELS: Record<CausalVistoBueno, string> = {
  faltas_puntualidad_asistencia: 'Faltas de puntualidad o asistencia, o abandono del trabajo',
  indisciplina_desobediencia: 'Indisciplina o desobediencia graves a los reglamentos',
  falta_probidad: 'Falta de probidad o conducta inmoral',
  injurias_graves: 'Injurias graves al empleador o su representante',
  ineptitud_manifiesta: 'Ineptitud manifiesta para la labor contratada',
  denuncia_injustificada_iess: 'Denuncia injustificada ante el Seguro Social',
  incumplimiento_seguridad: 'No acatar las medidas de seguridad e higiene',
}

export const CAUSAL_NUMERAL: Record<CausalVistoBueno, number> = {
  faltas_puntualidad_asistencia: 1,
  indisciplina_desobediencia: 2,
  falta_probidad: 3,
  injurias_graves: 4,
  ineptitud_manifiesta: 5,
  denuncia_injustificada_iess: 6,
  incumplimiento_seguridad: 7,
}

export function referenciaLegal(causal: CausalVistoBueno): string {
  return `Art. 172 núm. ${CAUSAL_NUMERAL[causal]} del Código del Trabajo`
}

export function nombreServidor(s?: ServidorResumen | null): string {
  if (!s) return '—'

  return [s.apellido, s.segundo_apellido, s.nombre, s.segundo_nombre]
    .filter(Boolean)
    .join(' ') || '—'
}

export function formatFecha(fecha?: string | null): string {
  if (!fecha) return '—'

  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC',
  })
}
