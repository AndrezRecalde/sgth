'use client'

import { Modal, Button, Group, Stack } from '@mantine/core'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useUnidadMutations } from '../hooks/useUnidadMutations'
import { UnidadForm } from './UnidadForm'
import type { UnidadFormData } from '../schemas/unidad.schema'
import type { UnidadConRelaciones } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  unidad?: UnidadConRelaciones | null
}

export function UnidadModal({ opened, onClose, unidad }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const { crear, editar } = useUnidadMutations()
  const isEditing = !!unidad

  const handleSubmit = (values: UnidadFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: Number(unidad!.id), data: values })
      : crear.mutateAsync(values)

    mutation.then(onClose).catch(() => {})
  }

  const isPending = crear.isPending || editar.isPending

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={isEditing ? 'Editar unidad administrativa' : 'Nueva unidad administrativa'}
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack>
        <UnidadForm
          initialValues={isEditing ? {
            nombre:            (unidad as UnidadConRelaciones).nombre,
            tipo_unidad_id:    (unidad as UnidadConRelaciones & { tipo_unidad_id?: number }).tipo_unidad_id as number,
            unidad_padre_id:   (unidad as UnidadConRelaciones & { unidad_padre_id?: number }).unidad_padre_id as number | null,
          } : undefined}
          onSubmit={handleSubmit}
          isPending={isPending}
        />
        <Group justify="flex-end" mt="md">
          <Button variant="default" onClick={onClose}>
            Cancelar
          </Button>
          <Button
            type="submit"
            form="unidad-form"
            loading={isPending}
            color="emerald"
          >
            {isEditing ? 'Actualizar' : 'Crear unidad'}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
