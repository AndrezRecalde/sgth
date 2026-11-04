'use client'

import { ModalFooter, SgthModal } from '@/components/ui'
import { usePuestoMutations } from '../hooks/usePuestoMutations'
import { PuestoForm } from './PuestoForm'
import type { PuestoFormData } from '../schemas/puesto.schema'
import type { PuestoConRelaciones } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  puesto?: PuestoConRelaciones | null
}

export function PuestoModal({ opened, onClose, puesto }: Props) {
  const { crear, editar } = usePuestoMutations()
  const editando = !!puesto

  // Si falla, el error sube al formulario, que lo pone en su campo.
  const guardar = async (valores: PuestoFormData) => {
    if (puesto) await editar.mutateAsync({ id: Number(puesto.id), data: valores })
    else await crear.mutateAsync(valores)
    onClose()
  }

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title={editando ? 'Editar puesto' : 'Nuevo puesto'}
      size="lg"
    >
      <PuestoForm
        valoresIniciales={puesto ? {
          cargo_id:                  puesto.cargo_id ?? undefined,
          unidad_administrativa_id:  puesto.unidad_administrativa_id,
          grupo_ocupacional_id:      puesto.grupo_ocupacional_id,
          partida_presupuestaria_id: puesto.partida_presupuestaria_id,
          plazas:                    puesto.plazas,
          rol_puesto:                puesto.rol_puesto,
          nivel_complejidad:         puesto.nivel_complejidad,
          regimen_laboral:           puesto.regimen_laboral,
          es_jefe:                   puesto.es_jefe,
          activo:                    puesto.activo,
        } : undefined}
        ocupado={!!puesto?.ocupado}
        onSubmit={guardar}
        tituloDelFallo={editando ? 'No se pudo actualizar el puesto' : 'No se pudo crear el puesto'}
      />
      <ModalFooter
        onCancel={onClose}
        form="puesto-form"
        submitLabel={editando ? 'Guardar cambios' : 'Crear puesto'}
        submitting={crear.isPending || editar.isPending}
      />
    </SgthModal>
  )
}
