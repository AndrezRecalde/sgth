import { useAuth } from '@/hooks/useAuth'

/**
 * El permiso de crear y corregir borradores de acciones de personal. Espeja
 * `Permiso::PREPARAR_ACCION_PERSONAL` del backend (diseño de Acciones de
 * Personal, 6.3).
 */
export const PERMISO_PREPARAR_ACCION = 'preparar-accion-personal'

/**
 * ¿Puede quien mira preparar acciones de personal para este servidor? Hace
 * falta el permiso y que no sea él mismo: nadie tramita sus propios actos
 * (TH 24). Sin servidor, solo mira el permiso.
 *
 * Es para esconder lo que no se puede usar; la regla la hace cumplir el
 * backend, que responde 403 o 422 igual.
 */
export function usePuedePrepararAccion(servidorId?: number | null): boolean {
  return usePuedePrepararAcciones()(servidorId)
}

/**
 * La misma pregunta para una lista: una tabla no puede llamar a un hook por
 * fila, así que recibe la función.
 */
export function usePuedePrepararAcciones(): (servidorId?: number | null) => boolean {
  const { hasPermiso, usuario } = useAuth()
  const conPermiso = hasPermiso(PERMISO_PREPARAR_ACCION)

  return (servidorId) => conPermiso && !(servidorId != null && usuario?.servidor_id === servidorId)
}
