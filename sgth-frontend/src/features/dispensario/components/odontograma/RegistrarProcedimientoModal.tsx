'use client'

import { Modal, Stack, Select, Textarea, Button, Group, Text, Badge } from '@mantine/core'
import { useState } from 'react'
import { IconCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useRegistrarProcedimiento } from '@/features/dispensario/hooks/useOdontograma'
import {
  PROCEDIMIENTO_OPTIONS, SUPERFICIE_OPTIONS, CONDICION_LABELS, CONDICION_COLORS,
} from '@/features/dispensario/services/odontogramaService'
import type { OdontogramaPieza, ProcedimientoOdontologico } from '@/features/dispensario/services/odontogramaService'

interface Props {
  opened:             boolean
  onClose:            () => void
  pieza:              OdontogramaPieza | null
  historiaClinicaId:  number
  consultaMedicaId?:  number | null
}

export function RegistrarProcedimientoModal({
  opened, onClose, pieza, historiaClinicaId, consultaMedicaId,
}: Props) {
  const contained = useContainedInput()
  const registrar = useRegistrarProcedimiento(historiaClinicaId)

  const [procedimiento, setProcedimiento] =
    useState<ProcedimientoOdontologico | null>(null)
  const [superficie, setSuperficie] = useState<string | null>(null)
  const [observaciones, setObservaciones] = useState('')

  const resetForm = () => {
    setProcedimiento(null)
    setSuperficie(null)
    setObservaciones('')
  }

  const handleSubmit = () => {
    if (!pieza || !procedimiento) return

    registrar.mutate(
      {
        odontograma_pieza_id: pieza.id,
        consulta_medica_id:   consultaMedicaId ?? null,
        procedimiento,
        superficie:           superficie || null,
        observaciones:        observaciones || null,
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
      title={pieza ? `Pieza ${pieza.numero_pieza}` : 'Registrar procedimiento'}
      size="sm"
      radius="xl"
    >
      <Stack gap="sm">
        {pieza && (
          <Group gap="xs">
            <Text size="xs" c="dimmed">Condición actual:</Text>
            <Badge size="xs" variant="light" color={CONDICION_COLORS[pieza.condicion]}>
              {CONDICION_LABELS[pieza.condicion]}
            </Badge>
          </Group>
        )}

        <Select
          label="Procedimiento"
          placeholder="Selecciona el procedimiento realizado"
          data={PROCEDIMIENTO_OPTIONS}
          {...contained}
          value={procedimiento}
          onChange={(v) => setProcedimiento(v as ProcedimientoOdontologico | null)}
          required
        />

        <Select
          label="Superficie (opcional)"
          placeholder="Selecciona la superficie dental"
          data={SUPERFICIE_OPTIONS}
          clearable
          {...contained}
          value={superficie}
          onChange={setSuperficie}
        />

        <Textarea
          label="Observaciones (opcional)"
          placeholder="Detalles adicionales del procedimiento"
          autosize
          minRows={2}
          {...contained}
          value={observaciones}
          onChange={(e) => setObservaciones(e.currentTarget.value)}
        />

        <Group justify="flex-end" mt="sm">
          <Button
            variant="default"
            onClick={() => { resetForm(); onClose() }}
          >
            Cancelar
          </Button>
          <Button
            color="emerald"
            leftSection={<IconCheck size={14} />}
            loading={registrar.isPending}
            disabled={!procedimiento}
            onClick={handleSubmit}
          >
            Registrar
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
