import { useAuthStore } from '@/store/auth.store'

const SIN_PERMISOS: string[] = []

/**
 * Qué puede hacer cada quien con el inventario de la farmacia (decisión del
 * Dispensario del 2026-10-24, pregunta 2). Todos lo consultan; lo que cambia
 * las existencias queda en el mostrador y la administración. La pantalla no
 * ofrece lo que el servidor rechazaría (la regla de la #529).
 *
 * Por permiso desde el PR 17a de docs/plan-roles-y-accesos.md: los mismos que
 * piden las rutas, que tienen los mismos roles que antes.
 */
export function usePermisosInventario() {
  const permisos = useAuthStore((s) => s.usuario?.permisos ?? SIN_PERMISOS)
  const gestiona = permisos.includes('gestionar-inventario-med')

  return {
    /** Dar de alta, editar, retirar o reactivar un medicamento del catálogo. */
    gestionaCatalogo: gestiona,
    /** Ajustar el stock tras un conteo físico. */
    ajustaStock:      gestiona,
    /** Registrar una adquisición, con su documento. */
    registraAdquisiciones: gestiona,
    /**
     * Dar de baja existencias vencidas, rotas o retiradas por el fabricante:
     * el mostrador, que es quien despacha.
     */
    daDeBaja:         permisos.includes('despachar-medicamento'),
  }
}
