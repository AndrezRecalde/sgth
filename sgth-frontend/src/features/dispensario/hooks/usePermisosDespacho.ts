import { useAuthStore } from '@/store/auth.store'
import type { RecetaMedica } from '../services/recetaService'

const SIN_ROLES: readonly string[] = []

/**
 * Qué puede hacer quien mira el despacho con cada receta. Son las reglas del
 * servidor:
 *
 * - Despachar, Enfermería, la administración del Dispensario y el auxiliar de
 *   farmacia: es el `role:` de `POST recetas/{id}/despachar`.
 * - Anular, la administración, o el médico u odontólogo que la emitió
 *   (`RecetaController::anular`).
 *
 * Despacho está en el menú de todo el personal del Dispensario, y la tabla
 * ofrecía las dos acciones a cualquiera: un médico rellenaba las cantidades
 * y una enfermera escribía el motivo de anulación, para recibir un 403.
 *
 * Se suscribe a los roles y no a `hasRole`, como `usePuedeTriar`.
 */
export function usePermisosDespacho() {
  const roles = useAuthStore((s) => s.usuario?.roles ?? SIN_ROLES)
  const usuarioId = useAuthStore((s) => s.usuario?.id)

  const esAdministracion = roles.includes('admin-dispensario')
  const puedeDespachar = esAdministracion
    || roles.includes('enfermera')
    || roles.includes('auxiliar-farmacia')

  const puedeAnular = (receta: RecetaMedica): boolean =>
    esAdministracion ||
    (usuarioId !== undefined && receta.consulta_medica?.medico_id === usuarioId)

  return { puedeDespachar, puedeAnular }
}
