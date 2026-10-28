import { useAuthStore } from '@/store/auth.store'
import type { RecetaMedica } from '../services/recetaService'

const SIN_PERMISOS: readonly string[] = []

/**
 * Qué puede hacer quien mira el despacho con cada receta. Son las reglas del
 * servidor:
 *
 * - Despachar, `despachar-medicamento`: Enfermería, la administración del
 *   Dispensario y el auxiliar de farmacia. Es lo que pide
 *   `POST recetas/{id}/despachar`.
 * - Anular, la administración (`supervisar-dispensario`), o el médico u
 *   odontólogo que la emitió (`RecetaController::anular`).
 *
 * Despacho está en el menú de todo el personal del Dispensario, y la tabla
 * ofrecía las dos acciones a cualquiera: un médico rellenaba las cantidades
 * y una enfermera escribía el motivo de anulación, para recibir un 403.
 *
 * Por permiso desde el PR 17a de docs/plan-roles-y-accesos.md.
 */
export function usePermisosDespacho() {
  const permisos = useAuthStore((s) => s.usuario?.permisos ?? SIN_PERMISOS)
  const usuarioId = useAuthStore((s) => s.usuario?.id)

  const supervisa = permisos.includes('supervisar-dispensario')
  const puedeDespachar = permisos.includes('despachar-medicamento')

  const puedeAnular = (receta: RecetaMedica): boolean =>
    supervisa ||
    (usuarioId !== undefined && receta.consulta_medica?.medico_id === usuarioId)

  return { puedeDespachar, puedeAnular }
}
