import { useAuth } from '@/hooks/useAuth'
import type { PermisoServidor } from '@/types/api'

/**
 * Qué puede hacer el usuario con sesión sobre un permiso.
 *
 * Es la misma regla que `PermisoServidorPolicy` en el backend: un botón que
 * acaba en 403 es peor que no mostrarlo. Hasta ahora la tabla ofrecía
 * confirmar, rechazar, validar y revertir a cualquiera que viera la fila, y el
 * detalle por folio del QR hacía lo mismo.
 *
 * - Confirmar y rechazar el documento: `confirmar-recepcion` (Recepción y
 *   Talento Humano). Quien lo recibe decide si llegó bien.
 * - Validar por Trabajo Social: `validar-trabajo-social`.
 * - Revertir una confirmación: `anular-permiso`, porque devuelve saldo.
 * - Anular un pendiente: el titular, o `anular-permiso-pendiente`.
 *
 * admin-ti no aparece aquí: el backend le deja todo por `Gate::before`, pero
 * es un rol técnico y no opera permisos. Si además tiene un rol de Talento
 * Humano, sus botones llegan por los permisos de ese rol.
 */
export function useAccionesPermiso() {
  const { usuario, hasPermiso } = useAuth()

  const recibeDocumentos = hasPermiso('confirmar-recepcion')
  const anulaCualquiera = hasPermiso('anular-permiso-pendiente')

  return {
    confirmar: recibeDocumentos,
    rechazar:  recibeDocumentos,
    validarTs: hasPermiso('validar-trabajo-social'),
    revertir:  hasPermiso('anular-permiso'),
    anular: (p: PermisoServidor) =>
      anulaCualquiera ||
      (usuario?.servidor_id != null && usuario.servidor_id === p.servidor_id),
  }
}

export type AccionesPermiso = ReturnType<typeof useAccionesPermiso>

