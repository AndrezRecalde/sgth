'use client'

import { Card, Divider, Group, Stack, Text, ThemeIcon, Timeline } from '@mantine/core'
import { IconHistory } from '@tabler/icons-react'
import { ESTADO_COLORS, ESTADO_LABELS } from '../constants/viatico.constants'
import { formatFechaHora } from '@/lib/fecha'
import type { ViaticoHistorialEstado } from '@/types/api'

interface Props {
  historial: ViaticoHistorialEstado[]
}

/**
 * Quién movió el viático, cuándo y por qué. Un paso sin autor lo dio la tarea
 * programada que sigue las fechas del viaje. El motivo importa sobre todo al
 * servidor: una liquidación devuelta a corrección no dice qué corregir en
 * ningún otro lugar.
 */
export function ViaticoHistorialCard({ historial }: Props) {
  if (historial.length === 0) return null

  return (
    <Card withBorder radius="md">
      <Group gap="xs" mb="sm">
        <ThemeIcon variant="default" size="sm">
          <IconHistory size={14} />
        </ThemeIcon>
        <Text fw={600} size="sm">
          Historial
        </Text>
      </Group>
      <Divider mb="sm" />

      <Timeline active={historial.length - 1} bulletSize={14} lineWidth={2}>
        {historial.map((paso) => (
          <Timeline.Item
            key={paso.id}
            color={ESTADO_COLORS[paso.estado_nuevo] ?? 'gray'}
            title={
              <Text size="sm" fw={500}>
                {ESTADO_LABELS[paso.estado_nuevo] ?? paso.estado_nuevo}
              </Text>
            }
          >
            <Stack gap={2}>
              <Text size="xs" c="dimmed">
                {formatFechaHora(paso.created_at)}
                {` · ${paso.usuario?.nombre_completo ?? 'Automático'}`}
              </Text>
              {paso.motivo && (
                <Text size="xs">
                  Motivo: {paso.motivo}
                </Text>
              )}
            </Stack>
          </Timeline.Item>
        ))}
      </Timeline>
    </Card>
  )
}
