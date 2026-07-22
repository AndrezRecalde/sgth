'use client'

import { useState } from 'react'
import { Modal, Button, Group, Textarea, Text } from '@mantine/core'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useSubrogacionMutations } from '../hooks/useSubrogacionMutations'

interface Props {
  opened: boolean
  onClose: () => void
  subrogacionId: number | null
}

export function CancelarSubrogacionModal({ opened, onClose, subrogacionId }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const { cancelar } = useSubrogacionMutations()
  const [motivo, setMotivo] = useState('')

  const handleClose = () => {
    setMotivo('')
    onClose()
  }

  const handleSubmit = () => {
    if (!subrogacionId || motivo.trim().length < 5) return
    cancelar.mutate({ id: subrogacionId, motivo }, { onSuccess: handleClose })
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Cancelar subrogación / encargo"
      size="sm"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Text size="sm" c="dimmed" mb="sm">
        Indique el motivo de la cancelación (mínimo 5 caracteres).
      </Text>
      <Textarea
        placeholder="Motivo de la cancelación"
        minRows={3}
        value={motivo}
        onChange={(e) => setMotivo(e.currentTarget.value)}
        error={motivo.length > 0 && motivo.trim().length < 5 ? 'Mínimo 5 caracteres' : undefined}
      />
      <Group justify="flex-end" mt="md">
        <Button variant="default" onClick={handleClose}>Volver</Button>
        <Button
          color="red" variant="light"
          loading={cancelar.isPending}
          disabled={motivo.trim().length < 5}
          onClick={handleSubmit}
        >
          Cancelar registro
        </Button>
      </Group>
    </Modal>
  )
}
