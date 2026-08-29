import type { SemanticTone } from '@/config/design.tokens'

/**
 * Régimen laboral de un servidor. Espeja `App\Enums\RegimenLaboral`.
 *
 * `servicios_profesionales` se agregó el 2026-08-29. Antes solo había dos
 * valores y los contratos de servicios profesionales se guardaban como Código
 * del Trabajo, así que un profesional contratado sobre un puesto LOSEP aparecía
 * con un régimen que nadie había elegido.
 *
 * OJO: los PUESTOS y los grupos ocupacionales siguen teniendo solo dos
 * regímenes. Un puesto es una plaza de la estructura, siempre LOSEP o Código
 * del Trabajo; el contrato civil se firma sobre un puesto, no crea uno propio.
 * Este módulo es para el régimen de la PERSONA.
 */
export type RegimenServidor =
  | 'losep'
  | 'codigo_trabajo'
  | 'servicios_profesionales'

export const REGIMEN_LABELS: Record<string, string> = {
  losep: 'LOSEP',
  codigo_trabajo: 'Código del Trabajo',
  servicios_profesionales: 'Servicios Profesionales',
}

/** Tono semántico: distingue los tres, sin sugerir que uno sea mejor. */
export const REGIMEN_TONOS: Record<string, SemanticTone> = {
  losep: 'info',
  codigo_trabajo: 'warning',
  servicios_profesionales: 'neutral',
}

/**
 * ¿Es una relación laboral de dependencia?
 *
 * Espeja `RegimenLaboral::esRelacionLaboral()`. De aquí cuelgan las
 * prestaciones: un contrato civil no genera vacaciones, no accede a permisos
 * y no marca biométrico.
 */
export function esRelacionLaboral(regimen?: string | null): boolean {
  return regimen !== 'servicios_profesionales'
}

/** Solo LOSEP accede al módulo de permisos. */
export function accedeAPermisos(regimen?: string | null): boolean {
  return regimen === 'losep'
}
