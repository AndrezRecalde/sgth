'use client'

import { Modal, Stack, Textarea, Button, Group, Text } from '@mantine/core'
import { useState } from 'react'
import { IconBan } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAnularProcedimiento } from '@/features/dispensario/hooks/useOdontograma'
import { PROCEDIMIENTO_OPTIONS } from '@/features/dispensario/services/odontogramaService'
import type { OdontogramaProcedimientoDetalle } from '@/features/dispensario/services/odontogramaService'

interface Props {
  opened:             boolean
  onClose:            () => void
  item:               OdontogramaProcedimientoDetalle | null
  numeroPieza?:       number
  historiaClinicaId:  number
  consultaMedicaId?:  number | null
}

export function AnularProcedimientoModal({
  opened, onClose, item, numeroPieza, historiaClinicaId, consultaMedicaId,
}: Props) {
  const contained = useContainedInput()
  const anular = useAnularProcedimiento(historiaClinicaId)

  const [motivo, setMotivo] = useState('')

  const getLabelProcedimiento = (valor: string) =>
    PROCEDIMIENTO_OPTIONS.find(o => o.value === valor)?.label ?? valor

  const resetForm = () => setMotivo('')

  const handleSubmit = () => {
    if (!item || !motivo.trim()) return

    anular.mutate(
      {
        id: item.id,
        data: {
          motivo_anulacion:   motivo.trim(),
          consulta_medica_id: consultaMedicaId ?? null,
        },
      },
      {
        onSuccess: () => {
          resetForm()
          onClose()
        },
      }
    )
  }

  return (
    <Modal
      opened={opened}
      onClose={() => { resetForm(); onClose() }}
      title="Anular procedimiento"
      size="sm"
      radius="xl"
    >
      <Stack gap="sm">
        {item && (
          <Text size="sm" c="dimmed">
            Vas a anular{' '}
            <Text span fw={600}>{getLabelProcedimiento(item.procedimiento)}</Text>
            {numeroPieza && <> en la pieza <Text span fw={600}>{numeroPieza}</Text></>}.
            Esta acción quedará registrada en el historial junto con el motivo.
          </Text>
        )}

        <Textarea
          label="Motivo de la anulación"
          placeholder="Ej: se registró el procedimiento equivocado por error"
          autosize
          minRows={2}
          {...contained}
          value={motivo}
          onChange={(e) => setMotivo(e.currentTarget.value)}
          required
        />

        <Group justify="flex-end" mt="sm">
          <Button
            variant="default"
            onClick={() => { resetForm(); onClose() }}
          >
            Cancelar
          </Button>
          <Button
            color="red"
            leftSection={<IconBan size={14} />}
            loading={anular.isPending}
            disabled={!motivo.trim()}
            onClick={handleSubmit}
          >
            Anular
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
