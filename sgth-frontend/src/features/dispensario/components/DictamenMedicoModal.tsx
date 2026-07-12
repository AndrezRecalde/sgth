'use client'

import {
  Modal, Stack, Text, Select, Textarea,
  Button, Group, Card, Badge, Alert,
} from '@mantine/core'
import { IconCheck, IconInfoCircle } from '@tabler/icons-react'
import { useState } from 'react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCompletarSolicitud } from '../hooks/useSolicitudCertificacion'
import type { SolicitudCertificacion } from
  '../services/solicitudCertificacionService'

interface Props {
  opened:    boolean
  onClose:   () => void
  solicitud: SolicitudCertificacion | null
  fichaFemoId?: number | null
}

const DICTAMEN_OPTIONS = [
  {
    value: 'apto',
    label: '✅ Apto — Sin restricciones',
  },
  {
    value: 'apto_con_restricciones',
    label: '⚠️ Apto con restricciones',
  },
  {
    value: 'no_apto',
    label: '❌ No apto',
  },
]

const DICTAMEN_COLORS: Record<string, string> = {
  apto:                   'emerald',
  apto_con_restricciones: 'orange',
  no_apto:                'red',
}

export function DictamenMedicoModal({
  opened, onClose, solicitud, fichaFemoId,
}: Props) {
  const contained  = useContainedInput()
  const completar  = useCompletarSolicitud()
  const [dictamen, setDictamen] =
    useState<'apto' | 'apto_con_restricciones' | 'no_apto' | null>(null)
  const [observacion, setObservacion] = useState('')

  const handleClose = () => {
    setDictamen(null)
    setObservacion('')
    onClose()
  }

  const handleGuardar = () => {
    if (!dictamen || !solicitud) return
    completar.mutate(
      {
        id: solicitud.id,
        data: {
          dictamen,
          observacion_medica: observacion.trim() || null,
          ficha_femo_id:      fichaFemoId ?? null,
        },
      },
      { onSuccess: handleClose }
    )
  }

  if (!solicitud) return null

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Emitir dictamen médico"
      size="md"
      radius="xl"
    >
      <Stack gap="md">
        <Card withBorder radius="md" p="sm">
          <Stack gap={2}>
            <Text size="sm" fw={600}>
              {solicitud.nombres_paciente}
            </Text>
            <Text size="xs" c="dimmed" ff="monospace">
              {solicitud.cedula_paciente}
            </Text>
            {solicitud.puesto_solicitado && (
              <Text size="xs" c="dimmed">
                {solicitud.puesto_solicitado}
              </Text>
            )}
          </Stack>
        </Card>

        <Alert color="blue" variant="light"
          icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            El dictamen médico será notificado a Talento
            Humano. Si el candidato es APTO, RRHH podrá
            confirmar su incorporación al sistema.
          </Text>
        </Alert>

        <Select
          label="Dictamen médico"
          placeholder="Seleccione el resultado"
          data={DICTAMEN_OPTIONS}
          required
          {...contained}
          value={dictamen}
          onChange={(v) =>
            setDictamen(
              v as 'apto' | 'apto_con_restricciones' | 'no_apto' | null
            )
          }
        />

        {dictamen && (
          <Badge
            size="lg"
            variant="light"
            color={DICTAMEN_COLORS[dictamen]}
            fullWidth
          >
            {DICTAMEN_OPTIONS.find(o => o.value === dictamen)?.label}
          </Badge>
        )}

        <Textarea
          label="Observaciones médicas"
          placeholder="Restricciones, recomendaciones o indicaciones adicionales"
          description="Obligatorio si el dictamen es 'Apto con restricciones' o 'No apto'"
          autosize
          minRows={3}
          {...contained}
          value={observacion}
          onChange={(e) => setObservacion(e.currentTarget.value)}
        />

        <Group justify="flex-end" mt="sm">
          <Button variant="default" onClick={handleClose}>
            Cancelar
          </Button>
          <Button
            color={dictamen ? DICTAMEN_COLORS[dictamen] : 'gray'}
            leftSection={<IconCheck size={14} />}
            disabled={!dictamen}
            loading={completar.isPending}
            onClick={handleGuardar}
          >
            Emitir dictamen
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
