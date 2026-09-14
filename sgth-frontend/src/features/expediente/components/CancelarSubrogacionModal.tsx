'use client'

import { useState } from 'react'
import { Textarea, Text } from '@mantine/core'
import { ModalFooter, SgthModal } from '@/components/ui'
import { useSubrogacionMutations } from '../hooks/useSubrogacionMutations'

interface Props {
  opened: boolean
  onClose: () => void
  subrogacionId: number | null
}

export function CancelarSubrogacionModal({ opened, onClose, subrogacionId }: Props) {
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
    <SgthModal
      opened={opened}
      onClose={handleClose}
      title="Cancelar subrogación / encargo"
      size="sm"
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
      <ModalFooter
        onCancel={handleClose}
        cancelLabel="Volver"
        submitLabel="Cancelar registro"
        submitting={cancelar.isPending}
        submitDisabled={motivo.trim().length < 5}
        destructiva
        onSubmit={handleSubmit}
      />
    </SgthModal>
  )
}
