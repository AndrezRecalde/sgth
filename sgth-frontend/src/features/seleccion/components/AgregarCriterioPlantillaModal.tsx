'use client'

import { useAgregarCriterioPlantilla } from '../hooks/usePlantilla'
import type { SeccionCriterio } from '../services/criterioService'
import { CriterioFormModal } from './criterio/CriterioFormModal'

interface Props {
  opened:         boolean
  onClose:        () => void
  plantillaId:    number
  seccionInicial: SeccionCriterio
}

/** Agregar un criterio a una plantilla de evaluación. */
export function AgregarCriterioPlantillaModal({ opened, onClose, plantillaId, seccionInicial }: Props) {
  const agregar = useAgregarCriterioPlantilla(plantillaId)

  return (
    <CriterioFormModal opened={opened} onClose={onClose} seccionInicial={seccionInicial}
      onGuardar={agregar.mutateAsync} enviando={agregar.isPending} />
  )
}
