'use client'

import { Modal, Button, Group, Stack } from '@mantine/core'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
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
  const { isMobile } = useMobileBreakpoint()
  const { crear, editar } = usePuestoMutations()
  const isEditing = !!puesto

  const handleSubmit = (values: PuestoFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: Number(puesto!.id), data: values })
      : crear.mutateAsync(values)
    mutation.then(onClose).catch(() => {})
  }

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={isEditing ? 'Editar puesto' : 'Nuevo puesto'}
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack>
        <PuestoForm
          initialValues={isEditing ? {
            denominacion:              puesto?.denominacion,
            unidad_administrativa_id:  puesto?.unidad_administrativa_id,

            grupo_ocupacional_id:      puesto?.grupo_ocupacional_id,
            partida_presupuestaria_id: puesto?.partida_presupuestaria_id,
            plazas:                    puesto?.plazas,
            rol_puesto:                puesto?.rol_puesto as any,
            nivel_complejidad:         puesto?.nivel_complejidad as any,

            regimen_laboral:           puesto?.regimen_laboral,
            es_jefe:                   puesto?.es_jefe,
            activo:                    puesto?.activo,
            mision:                    puesto?.mision,
          } : undefined}
          onSubmit={handleSubmit}
        />
        <Group justify="flex-end" mt="md">
          <Button variant="default" onClick={onClose}>Cancelar</Button>
          <Button
            type="submit"
            form="puesto-form"
            loading={crear.isPending || editar.isPending}
            color="emerald"
          >
            {isEditing ? 'Actualizar' : 'Crear puesto'}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
