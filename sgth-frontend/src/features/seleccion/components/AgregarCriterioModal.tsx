'use client'

import { useCrearCriterio } from '../hooks/useCriterio'
import type { SeccionCriterio } from '../services/criterioService'
import { CriterioFormModal } from './criterio/CriterioFormModal'

interface Props {
  opened:         boolean
  onClose:        () => void
  convocatoriaId: number
  seccionInicial: SeccionCriterio
}

/** Agregar un criterio a una convocatoria en borrador. El tope de 100 lo valida el backend. */
export function AgregarCriterioModal({ opened, onClose, convocatoriaId, seccionInicial }: Props) {
  const crear = useCrearCriterio(convocatoriaId)

  return (
    <CriterioFormModal opened={opened} onClose={onClose} seccionInicial={seccionInicial}
      onGuardar={crear.mutateAsync} enviando={crear.isPending} />
  )
}
