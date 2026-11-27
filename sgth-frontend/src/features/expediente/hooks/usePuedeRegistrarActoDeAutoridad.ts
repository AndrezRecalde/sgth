import { useAuth } from '@/hooks/useAuth'

/**
 * El permiso de los actos del Prefecto y del Viceprefecto. Espeja
 * `Permiso::REGISTRAR_ACTO_AUTORIDAD` del backend (decisión D3 del diseño de
 * «Autoridades electas», 2026-11-27).
 *
 * Va aparte de `preparar-accion-personal` porque los actos de una autoridad
 * no son acciones de personal: nacen de la credencial del CNE o de una
 * resolución del Consejo. Hoy lo tienen los mismos dos roles que antes
 * —admin-uath y asistente-uath—, así que nadie gana ni pierde acceso; lo que
 * se gana es poder separarlo desde Usuarios.
 */
export const PERMISO_ACTO_AUTORIDAD = 'registrar-acto-autoridad'

/**
 * ¿Puede quien mira registrar los actos de una autoridad electa?
 *
 * Es para esconder lo que no se puede usar; la regla la hace cumplir el
 * backend, que responde 403 igual.
 */
export function usePuedeRegistrarActoDeAutoridad(): boolean {
  return useAuth().hasPermiso(PERMISO_ACTO_AUTORIDAD)
}
