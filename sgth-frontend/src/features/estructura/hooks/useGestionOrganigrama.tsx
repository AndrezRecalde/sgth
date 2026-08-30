'use client'

import { useCallback, useState } from 'react'
import { useDisclosure } from '@mantine/hooks'
import { confirmar } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useUnidadMutations } from './useUnidadMutations'
import type { UnidadConRelaciones } from '@/types/api'

/** Permiso de Talento Humano para crear, editar y eliminar unidades. */
export const PERMISO_GESTIONAR_ORGANIGRAMA = 'gestionar-organigrama'

/**
 * Alta, edición y baja de unidades del organigrama.
 *
 * Vive en un hook y no en la vista porque son cuatro estados que se coordinan
 * entre sí —modal abierto, unidad en edición, padre preseleccionado y el
 * permiso— y la vista solo tiene que decidir qué pinta con ellos.
 *
 * `puedeGestionar` es la puerta de la interfaz, no la de los datos: quien
 * manda es la política del backend. Aquí solo evita ofrecer botones que
 * terminarían en un 403.
 */
export function useGestionOrganigrama() {
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso(PERMISO_GESTIONAR_ORGANIGRAMA)

  const { eliminar } = useUnidadMutations()
  const [modalAbierto, modal] = useDisclosure(false)
  const [enEdicion, setEnEdicion] = useState<UnidadConRelaciones | null>(null)
  // El padre completo y no solo su id: de su nivel sale cómo se llama lo que
  // se va a crear. Colgar de la institución da una unidad administrativa, no
  // un subproceso, y el modal se titulaba mal en ese caso.
  const [padre, setPadre] = useState<UnidadConRelaciones | null>(null)

  const crear = useCallback(() => {
    setEnEdicion(null)
    setPadre(null)
    modal.open()
  }, [modal])

  const editar = useCallback((unidad: UnidadConRelaciones) => {
    setEnEdicion(unidad)
    setPadre(null)
    modal.open()
  }, [modal])

  const agregarHija = useCallback((unidadPadre: UnidadConRelaciones) => {
    setEnEdicion(null)
    setPadre(unidadPadre)
    modal.open()
  }, [modal])

  const eliminarUnidad = useCallback((unidad: UnidadConRelaciones) => {
    confirmar({
      title: 'Eliminar unidad administrativa',
      message: (
        <>
          Se eliminará <b>{unidad.nombre}</b> del organigrama.
          No se puede deshacer.
        </>
      ),
      destructiva: true,
      confirmLabel: 'Eliminar',
      onConfirm: () => eliminar.mutate(Number(unidad.id)),
    })
  }, [eliminar])

  const cerrar = useCallback(() => {
    modal.close()
    setEnEdicion(null)
    setPadre(null)
  }, [modal])

  return {
    puedeGestionar,
    modalAbierto,
    enEdicion,
    padre,
    crear,
    // Sin permiso se devuelven sin definir, y los componentes que las reciben
    // no dibujan la acción: la comprobación se hace una vez, aquí.
    editar:         puedeGestionar ? editar         : undefined,
    agregarHija:    puedeGestionar ? agregarHija    : undefined,
    eliminarUnidad: puedeGestionar ? eliminarUnidad : undefined,
    cerrar,
  }
}
