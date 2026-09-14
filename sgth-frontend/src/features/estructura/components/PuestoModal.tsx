'use client'

import { Stack } from '@mantine/core'
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
  const isEditing = !!puesto

  const handleSubmit = (values: PuestoFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: Number(puesto!.id), data: values })
      : crear.mutateAsync(values)
    mutation.then(onClose).catch(() => {})
  }

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title={isEditing ? 'Editar puesto' : 'Nuevo puesto'}
      size="lg"
    >
      <Stack>
        <PuestoForm
          initialValues={isEditing ? {
            cargo_id:                 puesto?.cargo_id ?? undefined,
            unidad_administrativa_id: puesto?.unidad_administrativa_id,
            grupo_ocupacional_id:     puesto?.grupo_ocupacional_id,
            partida_presupuestaria_id: puesto?.partida_presupuestaria_id,
            plazas:                   puesto?.plazas,
            rol_puesto: puesto?.rol_puesto as PuestoFormData['rol_puesto'],
            nivel_complejidad: puesto?.nivel_complejidad as PuestoFormData['nivel_complejidad'],
            regimen_laboral:   puesto?.regimen_laboral,
            es_jefe:           puesto?.es_jefe,
            activo:            puesto?.activo,
          } : undefined}
          onSubmit={handleSubmit}
        />
      </Stack>
      <ModalFooter
        onCancel={onClose}
        form="puesto-form"
        submitLabel={isEditing ? 'Actualizar' : 'Crear puesto'}
        submitting={crear.isPending || editar.isPending}
      />
    </SgthModal>
  )
}
