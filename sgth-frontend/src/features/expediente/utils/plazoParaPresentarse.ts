import type { ClaseDelCatalogo } from '@/types/api'

/** El término del Reglamento a la LOSEP, Art. 20: 3 días hábiles, o hasta 5. */
export const DIAS_PARA_PRESENTARSE = 3
export const DIAS_MAXIMOS_PARA_PRESENTARSE = 5

/**
 * ¿El borrador fija su término para presentarse (fase 6.4)? Solo el ingreso
 * por acción de personal: el ocasional tiene sus 24 horas, y el contrato
 * civil, el obrero y la autoridad, sus propios documentos. Qué nombramientos
 * van por otro instrumento lo dice el catálogo.
 */
export function fijaTerminoParaPresentarse(
  clase: ClaseDelCatalogo | undefined,
  nombramiento: string | null | undefined,
): boolean {
  if (!clase?.tiene_plazo_para_presentarse || !nombramiento) return false

  const otrosInstrumentos: readonly string[] = [
    ...clase.registro_de_contrato_para,
    ...clase.contrato_civil_para,
    ...clase.documento_ct_para,
    ...clase.acto_de_autoridad_para,
  ]

  return !otrosInstrumentos.includes(nombramiento)
}

/** ¿Es el ingreso de un ocasional, que se presenta en 24 horas? */
export function presentaEnVeinticuatroHoras(
  clase: ClaseDelCatalogo | undefined,
  nombramiento: string | null | undefined,
): boolean {
  return !!clase?.tiene_plazo_para_presentarse
    && !!nombramiento
    && (clase.registro_de_contrato_para as readonly string[]).includes(nombramiento)
}
