import { useAuthStore } from '@/store/auth.store'

const SIN_PERMISOS: readonly string[] = []

/**
 * ¿Imprime, revoca y recibe tarjetas? Solo Comunicación Social (y admin-ti):
 * Talento Humano y Auditoría entran a la misma pantalla para consultar.
 *
 * Se suscribe a los permisos y no a `hasPermiso`, que es siempre la misma
 * función: con ella no se volvía a pintar al rehidratarse la sesión.
 */
export function usePuedeGestionarCredenciales(): boolean {
  const permisos = useAuthStore((s) => s.usuario?.permisos ?? SIN_PERMISOS)
  return permisos.includes('gestionar-credenciales')
}
