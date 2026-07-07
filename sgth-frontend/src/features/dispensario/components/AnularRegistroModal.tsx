'use client'

import {
  Modal, Stack, Select, Textarea,
  Button, Group, Text, Alert,
} from '@mantine/core'
import { useState } from 'react'
import { IconAlertTriangle, IconCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'

interface Props {
  opened:      boolean
  onClose:     () => void
  titulo:      string
  descripcion: string
  onConfirmar: (motivo: string) => void
  loading:     boolean
}

const MOTIVOS_PREDEFINIDOS = [
  { value: 'duplicado',            label: 'Registro duplicado'       },
  { value: 'error_digitacion',     label: 'Error de digitación'      },
  { value: 'diagnostico_incorrecto', label: 'Diagnóstico incorrecto' },
  { value: 'paciente_incorrecto',  label: 'Paciente incorrecto'      },
  { value: 'otro',                 label: 'Otro'                     },
]

export function AnularRegistroModal({
  opened, onClose, titulo, descripcion,
  onConfirmar, loading,
}: Props) {
  const contained = useContainedInput()
  const [motivoSel, setMotivoSel] = useState<string>('')
  const [motivoLibre, setMotivoLibre] = useState('')

  const motivoFinal = motivoSel === 'otro'
    ? motivoLibre.trim()
    : MOTIVOS_PREDEFINIDOS.find(m => m.value === motivoSel)?.label ?? ''

  const puedeConfirmar = !!motivoSel &&
    (motivoSel !== 'otro' || motivoLibre.trim().length >= 5)

  const handleConfirmar = () => {
    if (!puedeConfirmar) return
    onConfirmar(motivoFinal)
  }

  const handleClose = () => {
    setMotivoSel('')
    setMotivoLibre('')
    onClose()
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={titulo}
      size="sm"
      radius="xl"
    >
      <Stack gap="sm">
        <Alert
          icon={<IconAlertTriangle size={14} />}
          color="orange"
          variant="light"
        >
          <Text size="xs">
            <Text span fw={600}>{descripcion}</Text>
            {' '}Esta acción no se puede deshacer.
            El registro quedará anulado con trazabilidad.
          </Text>
        </Alert>

        <Select
          label="Motivo de anulación"
          placeholder="Seleccione un motivo"
          data={MOTIVOS_PREDEFINIDOS}
          {...contained}
          value={motivoSel}
          onChange={(v) => {
            setMotivoSel(v ?? '')
            if (v !== 'otro') setMotivoLibre('')
          }}
        />

        {motivoSel === 'otro' && (
          <Textarea
            label="Especifique el motivo"
            placeholder="Mínimo 5 caracteres"
            autosize
            minRows={2}
            {...contained}
            value={motivoLibre}
            onChange={(e) => setMotivoLibre(e.currentTarget.value)}
          />
        )}

        <Group justify="flex-end" mt="sm">
          <Button variant="default" onClick={handleClose}>
            Cancelar
          </Button>
          <Button
            color="orange"
            leftSection={<IconCheck size={14} />}
            disabled={!puedeConfirmar}
            loading={loading}
            onClick={handleConfirmar}
          >
            Confirmar anulación
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
