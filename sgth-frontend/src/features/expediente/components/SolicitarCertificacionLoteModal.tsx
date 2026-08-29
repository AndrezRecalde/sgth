'use client'

import {
  Modal, Stack, Select, Textarea,
  Button, Group, Text, List, ScrollArea,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useState } from 'react'
import { IconSend } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCrearSolicitudLote } from '@/features/dispensario/hooks/useSolicitudCertificacion'
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  opened:    boolean
  onClose:   () => void
  servidores: ServidorConRelaciones[]
}

const TIPO_EVENTO_LOTE_OPTIONS = [
  { value: 'periodica',  label: 'Periódica'  },
  { value: 'reintegro',  label: 'Reintegro'  },
  { value: 'retiro',     label: 'Retiro'     },
]

function fromDate(d: Date | null): string | null {
  if (!d) return null
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

function fechaLimitePorDefecto(): Date {
  const d = new Date()
  d.setDate(d.getDate() + 7)
  return d
}

export function SolicitarCertificacionLoteModal({
  opened, onClose, servidores,
}: Props) {
  const contained = useContainedInput()
  const crearLote  = useCrearSolicitudLote()

  const [tipoEvento, setTipoEvento] = useState<string | null>(null)
  const [fechaLimite, setFechaLimite] = useState<Date | null>(fechaLimitePorDefecto())
  const [observaciones, setObservaciones] = useState('')

  const resetForm = () => {
    setTipoEvento(null)
    setFechaLimite(fechaLimitePorDefecto())
    setObservaciones('')
  }

  const handleSubmit = () => {
    if (!tipoEvento) return

    crearLote.mutate(
      {
        servidor_ids:   servidores.map(s => s.id),
        tipo_evento:    tipoEvento as 'periodica' | 'reintegro' | 'retiro',
        fecha_limite:   fromDate(fechaLimite),
        observaciones:  observaciones || null,
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
      title="Solicitar certificación médica"
      size="md"
      radius="xl"
    >
      <Stack gap="sm">
        <Text size="sm" c="dimmed">
          Se generará una solicitud de certificación médica ocupacional
          para cada uno de los siguientes {servidores.length} servidor(es):
        </Text>

        <ScrollArea.Autosize mah={150} type="auto">
          <List size="sm" spacing={4}>
            {servidores.map(s => (
              <List.Item key={s.id}>
                {s.nombre} {s.apellido}
                {' '}
                <Text span size="xs" c="dimmed" ff="monospace">
                  ({s.cedula})
                </Text>
              </List.Item>
            ))}
          </List>
        </ScrollArea.Autosize>

        <Select
          label="Tipo de evento"
          placeholder="Selecciona el tipo de evaluación"
          data={TIPO_EVENTO_LOTE_OPTIONS}
          {...contained}
          value={tipoEvento}
          onChange={setTipoEvento}
          required
        />

        <DatePickerInput
          label="Fecha límite"
          placeholder="Selecciona la fecha límite"
          valueFormat="DD/MM/YYYY"
          {...contained}
          value={fechaLimite}
          onChange={(v) => setFechaLimite(v ? new Date(v) : null)}
        />

        <Textarea
          label="Observaciones (opcional)"
          placeholder="Motivo o indicaciones adicionales"
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
            leftSection={<IconSend size={14} />}
            loading={crearLote.isPending}
            disabled={!tipoEvento || servidores.length === 0}
            onClick={handleSubmit}
          >
            Enviar solicitudes
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
