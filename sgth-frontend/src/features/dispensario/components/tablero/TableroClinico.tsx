'use client'

import { Group, Progress, SimpleGrid, Stack, Text } from '@mantine/core'
import { IconDental, IconStethoscope } from '@tabler/icons-react'
import { SectionCard, StatusBadge } from '@/components/ui'
import { ETIQUETA_ESPECIALIDAD } from '../../services/kpisService'
import type { Especialidad, KpisDispensario } from '../../services/kpisService'

interface Props {
  kpis?: KpisDispensario
}

function InsigniaEspecialidad({ valor }: { valor: Especialidad }) {
  return <StatusBadge size="xs">{ETIQUETA_ESPECIALIDAD[valor]}</StatusBadge>
}

/** Lo clínico del período: el reparto, quién atendió y qué se diagnosticó. */
export function TableroClinico({ kpis }: Props) {
  const general = kpis?.atenciones_por_especialidad.medicina_general ?? 0
  const odonto  = kpis?.atenciones_por_especialidad.odontologia ?? 0
  const total   = general + odonto
  const profesionales = kpis?.consultas_por_medico ?? []
  const diagnosticos  = kpis?.top_diagnosticos ?? []

  return (
    <Stack gap="md">
      {total > 0 && (
        <SectionCard title="Reparto por especialidad" description="Sobre las atenciones del período.">
          <Stack gap="xs">
            <Progress.Root size="xl" radius="md">
              <Progress.Section value={(general / total) * 100} color="ocean">
                {general > 0 && <Progress.Label>{general}</Progress.Label>}
              </Progress.Section>
              <Progress.Section value={(odonto / total) * 100} color="emerald">
                {odonto > 0 && <Progress.Label>{odonto}</Progress.Label>}
              </Progress.Section>
            </Progress.Root>
            <Group gap="lg">
              <Group gap={6}>
                <IconStethoscope size={14} color="var(--mantine-color-ocean-6)" />
                <Text size="xs" c="dimmed">Medicina general</Text>
              </Group>
              <Group gap={6}>
                <IconDental size={14} color="var(--mantine-color-emerald-6)" />
                <Text size="xs" c="dimmed">Odontología</Text>
              </Group>
            </Group>
          </Stack>
        </SectionCard>
      )}

      <SimpleGrid cols={{ base: 1, lg: 2 }} spacing="md">
        <SectionCard title="Consultas por profesional" description="En el período, separadas por especialidad.">
          {profesionales.length === 0 ? (
            <Text size="sm" c="dimmed">No hay consultas registradas en el período.</Text>
          ) : (
            <Stack gap="xs">
              {profesionales.map((fila, i) => (
                <Group key={`${fila.medico}-${fila.especialidad}-${i}`} gap="xs" wrap="nowrap">
                  <Text size="sm" flex={1} lineClamp={1}>{fila.medico}</Text>
                  <InsigniaEspecialidad valor={fila.especialidad} />
                  <Text size="sm" fw={600}>{fila.total_consultas}</Text>
                </Group>
              ))}
            </Stack>
          )}
        </SectionCard>

        <SectionCard title="Diagnósticos más frecuentes" description="Los cinco más registrados del período, con su CIE-10.">
          {diagnosticos.length === 0 ? (
            <Text size="sm" c="dimmed">Ninguna consulta del período lleva todavía un código CIE-10.</Text>
          ) : (
            <Stack gap="xs">
              {diagnosticos.map((d) => (
                <Group key={`${d.codigo}-${d.especialidad}`} gap="xs" wrap="nowrap">
                  <StatusBadge variant="outline" ff="monospace">{d.codigo}</StatusBadge>
                  <Text size="xs" flex={1} lineClamp={1}>{d.descripcion}</Text>
                  <InsigniaEspecialidad valor={d.especialidad} />
                  <Text size="sm" fw={600}>{d.total}</Text>
                </Group>
              ))}
            </Stack>
          )}
        </SectionCard>
      </SimpleGrid>
    </Stack>
  )
}
