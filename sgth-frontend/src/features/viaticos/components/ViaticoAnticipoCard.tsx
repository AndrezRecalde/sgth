'use client'

import {
  Card, Group, Text, Badge,
  Divider, Stack, ThemeIcon,
} from '@mantine/core'
import { IconCurrencyDollar } from '@tabler/icons-react'
import type { ViaticoConRelaciones } from '@/types/api'

interface Props {
  viatico: ViaticoConRelaciones
}

function fmtMonto(v?: number | string | null): string {
  if (v == null) return '—'
  return `$${Number(v).toFixed(2)}`
}

const MODALIDAD_LABELS: Record<string, string> = {
  total:        'Anticipo total',
  sin_anticipo: 'Sin anticipo',
}

export function ViaticoAnticipoCard({ viatico: d }: Props) {
  return (
    <Card withBorder radius="md" h="100%">
      <Group gap="xs" mb="sm">
        <ThemeIcon variant="default" size="sm">
          <IconCurrencyDollar size={14} />
        </ThemeIcon>
        <Text fw={600} size="sm">Anticipo y monto</Text>
      </Group>
      <Divider mb="sm" />
      <Stack gap="xs">
        <Group justify="space-between">
          <Text size="xs" c="dimmed">Modalidad</Text>
          <Badge size="sm" color="orange" variant="light">
            {MODALIDAD_LABELS[d.modalidad_anticipo ?? '']
              ?? d.modalidad_anticipo}
          </Badge>
        </Group>
        <Group justify="space-between">
          <Text size="xs" c="dimmed">Monto calculado</Text>
          <Text fw={700} c="emerald" size="md">
            {fmtMonto(d.monto_calculado)}
          </Text>
        </Group>
        <Group justify="space-between">
          <Text size="xs" c="dimmed">Anticipo a entregar</Text>
          <Text fw={600} size="sm">
            {fmtMonto(d.monto_anticipo)}
          </Text>
        </Group>
      </Stack>
    </Card>
  )
}
