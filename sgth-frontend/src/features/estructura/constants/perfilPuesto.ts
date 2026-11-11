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

/** El formato de asignación de responsabilidades trae tres filas de cada tipo. */
export const ESPACIOS_POR_TIPO_EN_LA_ASIGNACION = 3
