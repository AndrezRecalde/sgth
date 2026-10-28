import { useAuthStore } from '@/store/auth.store'

const SIN_PERMISOS: readonly string[] = []

/**
 * ¿Puede registrar un triaje? `registrar-triaje`, que es lo que pide
 * `POST agenda/{id}/triaje`: Enfermería y la administración del Dispensario
 * (PR 17b; antes, el `role:`). Al resto se le ofrecía «Tomar triaje», llenaba
 * las ocho cifras y recibía un 403.
 *
 * Se suscribe a los permisos y no a `hasPermiso`, que es siempre la misma
 * función: con ella no se volvía a pintar al terminar de rehidratarse la
 * sesión.
 */
export function usePuedeTriar(): boolean {
  const permisos = useAuthStore((s) => s.usuario?.permisos ?? SIN_PERMISOS)
  return permisos.includes('registrar-triaje')
}
