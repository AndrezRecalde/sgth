'use client'

import { Avatar, Button, Card, Group, Stack, Text } from '@mantine/core'
import { IconArrowRight, IconUser, IconUsers } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { ESTADO_TURNO_LABELS, TONO_TURNO } from '../constants/turnos'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  turno:         AgendaMedica
  totalEnEspera: number
  onFinalizar:   () => void
}

/**
 * Quién se está atendiendo, en qué estado y con cuánta gente detrás.
 *
 * Se queda arriba del todo y se va con el desplazamiento: el panel lateral
 * repite el nombre precisamente porque esta tarjeta desaparece al bajar por el
 * formulario.
 */
export function CabeceraPacienteAtencion({
  turno, totalEnEspera, onFinalizar,
}: Props) {
  const esServidor = !!turno.servidor_id
  const nombre = esServidor
    ? `${turno.servidor?.nombre ?? ''} ${turno.servidor?.apellido ?? ''}`
    : `${turno.carga_familiar?.nombres ?? ''} ${turno.carga_familiar?.apellidos ?? ''}`

  return (
    <Card withBorder radius="lg" p="sm">
      <Group justify="space-between" wrap="nowrap">
        <Group gap="sm" wrap="nowrap">
          <Avatar radius="xl" size="md">
            {esServidor ? <IconUser size={16} /> : <IconUsers size={16} />}
          </Avatar>
          <Stack gap={0}>
            <Group gap="xs">
              <Text size="sm" fw={700}>
                {nombre.trim() || '—'}
              </Text>
              <StatusBadge tone={TONO_TURNO[turno.estado] ?? 'neutral'} size="xs">
                {ESTADO_TURNO_LABELS[turno.estado] ?? turno.estado}
              </StatusBadge>
            </Group>
            <Text size="xs" c="dimmed" ff="monospace">
              {turno.folio} · {esServidor ? 'Servidor' : 'Familiar'}
            </Text>
          </Stack>
        </Group>

        <Group gap="xs" wrap="nowrap">
          {totalEnEspera > 0 && (
            <StatusBadge>{totalEnEspera} en espera</StatusBadge>
          )}
          <Button
            size="xs"
            variant="light"
            rightSection={<IconArrowRight size={13} />}
            onClick={onFinalizar}
          >
            Finalizar
          </Button>
        </Group>
      </Group>
    </Card>
  )
}
