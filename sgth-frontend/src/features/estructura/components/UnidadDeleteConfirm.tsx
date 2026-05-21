'use client'

import { Modal, Text, Button, Group, Stack } from '@mantine/core'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useUnidadMutations } from '../hooks/useUnidadMutations'
import type { UnidadConRelaciones } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  unidad: UnidadConRelaciones | null
}

export function UnidadDeleteConfirm({ opened, onClose, unidad }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const { eliminar } = useUnidadMutations()

  const handleEliminar = () => {
    if (!unidad) return
    eliminar.mutateAsync(Number(unidad.id))
      .then(onClose)
      .catch(() => {})
  }

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="Eliminar unidad administrativa"
      size="sm"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack>
        <Text size="sm">
          ¿Eliminar la unidad{' '}
          <Text span fw={600}>{unidad?.nombre}</Text>?
          Esta acción no se puede deshacer.
        </Text>
        <Group justify="flex-end">
          <Button variant="default" onClick={onClose}>
            Cancelar
          </Button>
          <Button
            color="red"
            loading={eliminar.isPending}
            onClick={handleEliminar}
          >
            Eliminar
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
