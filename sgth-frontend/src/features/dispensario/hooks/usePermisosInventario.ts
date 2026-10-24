import { useAuthStore } from '@/store/auth.store'

const SIN_ROLES: string[] = []

/**
 * Qué puede hacer cada rol con el inventario de la farmacia (decisión del
 * Dispensario del 2026-10-24, pregunta 2). Todos lo consultan; lo que cambia
 * las existencias queda en el mostrador y la administración. La pantalla no
 * ofrece lo que el servidor rechazaría (la regla de la #529).
 */
export function usePermisosInventario() {
  const roles = useAuthStore((s) => s.usuario?.roles ?? SIN_ROLES)
  const esAdministracion = roles.includes('admin-dispensario')

  return {
    /** Dar de alta, editar, retirar o reactivar un medicamento del catálogo. */
    gestionaCatalogo: esAdministracion,
    /** Ajustar el stock tras un conteo físico. */
    ajustaStock:      esAdministracion,
    /** Registrar una adquisición, con su documento. */
    registraAdquisiciones: esAdministracion,
    /** Dar de baja existencias vencidas, rotas o retiradas por el fabricante. */
    daDeBaja:         esAdministracion || roles.includes('enfermera'),
  }
}
