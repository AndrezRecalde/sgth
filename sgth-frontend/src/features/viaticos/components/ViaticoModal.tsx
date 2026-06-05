'use client'

import { Modal, Text } from '@mantine/core'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'

interface Props {
  opened:  boolean
  onClose: () => void
}

export function ViaticoModal({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint()
  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="Nueva solicitud de viático"
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Text c="dimmed" size="sm">
        Formulario en construcción — próxima iteración.
      </Text>
    </Modal>
  )
}
