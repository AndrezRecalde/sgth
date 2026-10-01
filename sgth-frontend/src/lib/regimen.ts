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

/**
 * ¿Accede al módulo de permisos?
 *
 * Espeja `RegimenLaboral::accedeAPermisos()`: LOSEP y Código del Trabajo. Un
 * contrato civil no tiene jornada que permisar.
 *
 * El Código del Trabajo entró el 2026-09-30, cuando Talento Humano confirmó
 * que los obreros piden permisos como el resto y que su permiso personal les
 * descuenta de vacaciones igual que a un LOSEP.
 */
export function accedeAPermisos(regimen?: string | null): boolean {
  return regimen === 'losep' || regimen === 'codigo_trabajo'
}

/**
 * ¿Genera vacaciones?
 *
 * Espeja `RegimenLaboral::generaVacaciones()`. Un contrato civil no tiene
 * jornada ni relación de dependencia: no es que le toquen cero días, es que no
 * le corresponde el período.
 *
 * El backend lo rechaza igual; esto evita ofrecer en un selector a alguien a
 * quien después se le va a negar la solicitud.
 */
export function generaVacaciones(regimen?: string | null): boolean {
  return esRelacionLaboral(regimen)
}
