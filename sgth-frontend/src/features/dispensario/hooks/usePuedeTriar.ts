import { useAuthStore } from '@/store/auth.store'

const SIN_ROLES: readonly string[] = []

/**
 * ¿Puede registrar un triaje? Enfermería y la administración del
 * Dispensario: es el `role:` de `POST agenda/{id}/triaje`. Al resto se le
 * ofrecía «Tomar triaje», llenaba las ocho cifras y recibía un 403.
 *
 * Se suscribe a los roles y no a `hasRole`, que es siempre la misma función:
 * con ella no se volvía a pintar al terminar de rehidratarse la sesión.
 */
export function usePuedeTriar(): boolean {
  const roles = useAuthStore((s) => s.usuario?.roles ?? SIN_ROLES)
  return roles.includes('enfermera') || roles.includes('admin-dispensario')
}
