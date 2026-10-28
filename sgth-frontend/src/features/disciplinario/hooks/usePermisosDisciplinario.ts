import { useAuthStore } from '@/store/auth.store'

const SIN_PERMISOS: readonly string[] = []

/**
 * Qué puede hacer quien mira Régimen disciplinario. Los mismos permisos que
 * piden las rutas desde el 2026-10-28 (docs/revision-permisos-sin-uso.md, D8):
 * hoy los tres los tiene solo admin-uath, pero TI puede dar `ver-sumarios` a
 * alguien desde Usuarios, y esa persona no debe ver botones que le respondan
 * 403.
 *
 * Se suscribe a los permisos y no a `hasPermiso`, que es siempre la misma
 * función: con ella no se volvía a pintar al rehidratarse la sesión.
 */
export function usePermisosDisciplinario() {
  const permisos = useAuthStore((s) => s.usuario?.permisos ?? SIN_PERMISOS)

  return {
    /** Abrir y hacer avanzar sumarios, vistos buenos y el reglamento interno. */
    gestiona: permisos.includes('gestionar-sumarios'),
    /** Resolver un sumario, que impone la sanción. */
    sanciona: permisos.includes('registrar-sancion'),
  }
}
