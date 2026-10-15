'use client'

import { Alert, Group, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconCalendarEvent } from '@tabler/icons-react'
import { SectionCard } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { TableroIdentidad as Datos } from '../services/tableroService'

/**
 * Los logos en el Inicio de la persona designada (D2): si falta una variante
 * obligatoria, cuáles se ven con su respaldo y qué cambios ya están programados.
 */
export function TableroIdentidad({ datos }: { datos: Datos }) {
  const alDia = !datos.faltan_obligatorias.length && !datos.con_respaldo.length && !datos.programadas.length

  return (
    <SectionCard title="Logos de la institución" description="Lo que sale en el sistema y en los documentos.">
      <Stack gap="sm">
        {datos.faltan_obligatorias.length > 0 && (
          <Alert
            variant="light"
            color="red"
            icon={<IconAlertTriangle size={18} />}
            title={datos.faltan_obligatorias.length === 1 ? 'Falta una variante obligatoria' : 'Faltan variantes obligatorias'}
          >
            {datos.faltan_obligatorias.join(', ')}. Cárguela en Identidad institucional.
          </Alert>
        )}

        {datos.con_respaldo.length > 0 && (
          <Text size="sm">
            <Text span fw={600}>Se ven con su respaldo:</Text> {datos.con_respaldo.join(', ')}. Puede cargar una versión
            propia de cada una si la institución la tiene.
          </Text>
        )}

        {datos.programadas.map((p) => (
          <Group key={`${p.variante}-${p.vigente_desde}`} gap="xs" wrap="nowrap">
            <IconCalendarEvent size={16} color="var(--mantine-color-dimmed)" />
            <Text size="sm">
              {p.variante} cambia el {formatFecha(p.vigente_desde)}.
            </Text>
          </Group>
        ))}

        {alDia && <Text size="sm" c="dimmed">Todas las variantes tienen su versión propia y no hay cambios programados.</Text>}
      </Stack>
    </SectionCard>
  )
}
