import { useMemo, useState } from 'react'
import { usePermisos, usePermisosUsuario } from './usePermisos'
import type { PermisoGrupo, PermisoItem, Usuario } from '@/types/api'

/**
 * Estado de la selección de permisos directos de un usuario.
 *
 * Vivía dentro de PermisosDrawer, que así hacía cuatro cosas a la vez. Aquí
 * queda la parte que no se ve —qué está marcado, qué llega heredado por rol y
 * qué se envía al guardar— y el componente se limita a pintarla.
 */
export function useSeleccionPermisos(usuario: Usuario | null, opened: boolean) {
  const { data: grupos = [] } = usePermisos()

  const usuarioId = usuario?.id ? Number(usuario.id) : null

  const { data: permisosUsuario, isLoading: cargando } =
    usePermisosUsuario(opened ? usuarioId : null)

  const [permisosActivos, setPermisosActivos] = useState<string[]>([])

  // Los permisos cargados solo siembran la selección: a partir de ahí el
  // usuario marca y desmarca. Se resiembra al abrir el panel sobre otro
  // usuario, ajustando el estado durante el render en vez de en un efecto.
  const semilla = opened && usuarioId !== null && permisosUsuario
    ? String(usuarioId)
    : null
  const [semillaAplicada, setSemillaAplicada] = useState<string | null>(null)

  if (semilla !== semillaAplicada) {
    setSemillaAplicada(semilla)

    // Al cerrar (semilla null) se olvida la semilla aplicada —para que la
    // próxima apertura vuelva a sembrar desde el servidor— pero NO se vacía la
    // selección: hacerlo dejaba las casillas desmarcadas y el contador del pie
    // en 0 a la vista, durante los ~200 ms que tarda el panel en salir.
    if (semilla !== null) {
      setPermisosActivos(
        (permisosUsuario ?? []).map((p: PermisoItem) => p.nombre)
      )
    }
  }

  /** Los que ya le llegan por alguno de sus roles: se muestran bloqueados. */
  const permisosCubiertos = useMemo(() => {
    const roles = usuario?.roles ?? []
    return new Set(
      (grupos as PermisoGrupo[]).flatMap(g =>
        g.permisos
          .filter((p: PermisoItem) => roles.some(r => p.roles?.includes(r)))
          .map((p: PermisoItem) => p.nombre)
      )
    )
  }, [grupos, usuario])

  const togglePermiso = (nombre: string) => {
    if (permisosCubiertos.has(nombre)) return
    setPermisosActivos(prev =>
      prev.includes(nombre)
        ? prev.filter(p => p !== nombre)
        : [...prev, nombre]
    )
  }

  /** Lo que se envía: solo lo extra, nunca lo que ya cubre un rol. */
  const seleccionados = permisosActivos.filter(p => !permisosCubiertos.has(p))

  return {
    usuarioId,
    grupos: grupos as PermisoGrupo[],
    cargando,
    permisosActivos,
    permisosCubiertos,
    seleccionados,
    togglePermiso,
  }
}
