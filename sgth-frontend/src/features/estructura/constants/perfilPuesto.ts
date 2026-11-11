import type { FrecuenciaActividad, NivelCompetencia, TipoCompetencia } from '@/types/api'

/**
 * Las opciones del perfil del puesto, con los mismos nombres que el formato
 * de asignación de responsabilidades del Ministerio (IN-GEP-02-02-FOR-02).
 */
export const FRECUENCIAS: { value: FrecuenciaActividad; label: string }[] = [
  { value: 'diario_mes_integral', label: 'Diario, mes integral' },
  { value: 'diario_ordinario',    label: 'Diario ordinario' },
  { value: 'semanal',             label: 'Semanal' },
  { value: 'quincenal',           label: 'Quincenal' },
  { value: 'mensual',             label: 'Mensual' },
  { value: 'trimestral',          label: 'Trimestral' },
  { value: 'semestral',           label: 'Semestral' },
  { value: 'anual',               label: 'Anual' },
]

export function etiquetaFrecuencia(frecuencia: FrecuenciaActividad): string {
  return FRECUENCIAS.find(f => f.value === frecuencia)?.label ?? frecuencia
}

export const NIVELES_COMPETENCIA: { value: NivelCompetencia; label: string }[] = [
  { value: 'alto',  label: 'Alto' },
  { value: 'medio', label: 'Medio' },
  { value: 'bajo',  label: 'Bajo' },
]

export const TIPOS_COMPETENCIA: { value: TipoCompetencia; label: string; plural: string }[] = [
  { value: 'tecnica',    label: 'Técnica',    plural: 'Competencias técnicas' },
  { value: 'conductual', label: 'Conductual', plural: 'Competencias conductuales' },
]

/** «Complejidad alta», no «alto»: el valor guardado concuerda con «nivel». */
const COMPLEJIDAD_EN_FEMENINO: Record<string, string> = { bajo: 'baja', medio: 'media', alto: 'alta' }

export function complejidadEnFemenino(complejidad: string): string {
  return COMPLEJIDAD_EN_FEMENINO[complejidad] ?? complejidad
}

/**
 * El nivel con que se propone una competencia nueva. Sale del grado del puesto
 * (D5: hasta el 7, bajo; del 8 al 13, medio; del 14 en adelante, alto), que lo
 * calcula el backend. Sin grado —un puesto de obreros— queda la complejidad,
 * que usa la misma escala, y si tampoco hay, medio.
 */
export function nivelSugerido(
  porGrado: string | null | undefined,
  complejidad: string | null | undefined,
): NivelCompetencia {
  const deLaEscala = (valor: string | null | undefined) => NIVELES_COMPETENCIA.find(n => n.value === valor)?.value
  return deLaEscala(porGrado) ?? deLaEscala(complejidad) ?? 'medio'
}

/**
 * `NivelInstruccion` del backend: lo que pide un puesto, como lo escribe el
 * Manual de Puestos. Los estudios en curso son el «tercer año o sexto semestre
 * aprobado», que va con texto.
 */
export const NIVELES_INSTRUCCION = [
  { value: 'bachiller',                    label: 'Bachiller' },
  { value: 'tecnico_superior',             label: 'Técnico superior' },
  { value: 'tecnologico_superior',         label: 'Tecnológico superior' },
  { value: 'estudios_superiores_en_curso', label: 'Estudios superiores en curso' },
  { value: 'tercer_nivel',                 label: 'Tercer nivel de grado' },
  { value: 'cuarto_nivel',                 label: 'Cuarto nivel (posgrado)' },
] as const

/** `NivelDelPuesto` del backend. */
export const NIVELES_DEL_PUESTO = [
  { value: 'profesional',    label: 'Profesional' },
  { value: 'no_profesional', label: 'No profesional' },
] as const

/** «2 años y 6 meses», «6 años», «sin experiencia»: como lo escribe el manual. */
export function textoExperiencia(meses: number): string {
  if (meses <= 0) return 'sin experiencia'
  const anios = Math.floor(meses / 12)
  const resto = meses % 12
  const partes = [
    anios > 0 ? `${anios} ${anios === 1 ? 'año' : 'años'}` : null,
    resto > 0 ? `${resto} ${resto === 1 ? 'mes' : 'meses'}` : null,
  ].filter(Boolean)
  return partes.join(' y ')
}

/** El formato de asignación de responsabilidades trae tres filas de cada tipo. */
export const ESPACIOS_POR_TIPO_EN_LA_ASIGNACION = 3
