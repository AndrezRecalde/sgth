import { useAuthStore } from '@/store/auth.store'

/**
 * Si el usuario con sesión está vinculado a una ficha de servidor.
 *
 * Una cuenta puede no tenerla —la técnica de TIC, la que se creó antes que
 * la ficha— y todo lo «mío» del Portal sale de ella: documentos, vacaciones,
 * actividades, préstamos. Cada pantalla lo miraba a su manera, `servidor_id`
 * o `servidor`, y la mayoría no lo miraba (docs/plan-roles-y-accesos.md,
 * PR 15).
 */
export function useTieneFicha(): boolean {
  return useAuthStore((s) => !!s.usuario?.servidor_id)
}
