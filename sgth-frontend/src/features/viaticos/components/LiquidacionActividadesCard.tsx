'use client'

import { Button, Group, Paper, Stack, Text } from '@mantine/core'
import { IconClipboardList, IconPencil } from '@tabler/icons-react'
import { CountBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { ActividadData } from '../schemas/liquidacion.schema'

interface Props {
  actividades: ActividadData[]
  /** Sin estas dos, el bloque es de solo lectura. */
  onRegistrar?: () => void
  onEditar?: () => void
}

/** El informe de actividades: qué se hizo cada día de la comisión y dónde. */
export function LiquidacionActividadesCard({ actividades, onRegistrar, onEditar }: Props) {
  return (
    <Paper withBorder radius="md" p="md" h="100%">
      <Group justify="space-between" mb="sm">
        <Text fw={600} size="sm">Informe de actividades</Text>
        {actividades.length > 0 && <CountBadge>{actividades.length}</CountBadge>}
      </Group>

      {actividades.length === 0 ? (
        <Stack gap="sm">
          <Text size="sm" c="dimmed">
            Registre lo que hizo en cada día de la comisión.
          </Text>
          {onRegistrar && (
            <Button variant="light" leftSection={<IconClipboardList size={16} />} onClick={onRegistrar}>
              Registrar actividades
            </Button>
          )}
        </Stack>
      ) : (
        <Stack gap="sm">
          {actividades.map((a, i) => (
            <Stack key={i} gap={2}>
              {/* `fecha` es una fecha sin hora: formatFecha la lee como tal. */}
              <Text size="sm" fw={500}>
                {formatFecha(a.fecha)} · {a.lugar}
              </Text>
              {a.descripcion && <Text size="xs" c="dimmed">{a.descripcion}</Text>}
            </Stack>
          ))}
          {onEditar && (
            <Button variant="subtle" size="xs" leftSection={<IconPencil size={14} />} onClick={onEditar}>
              Editar actividades
            </Button>
          )}
        </Stack>
      )}
    </Paper>
  )
}
