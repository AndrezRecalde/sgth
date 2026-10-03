'use client'

import { Group, SimpleGrid, Stack, Text } from '@mantine/core'
import { IconClockHour4, IconUserCheck, IconUserX, IconUsersGroup } from '@tabler/icons-react'
import { SectionCard, StatCard, StatusBadge } from '@/components/ui'
import type { PanoramaDispensario } from '../../services/panoramaService'

interface Props {
  panorama?: PanoramaDispensario
  cargando: boolean
}

/** «45 min», «1 h 20 min», o un guion si no hubo consultas con turno. */
function minutos(m: number | null | undefined): string {
  if (m === null || m === undefined) return '—'
  return m < 60 ? `${m} min` : `${Math.floor(m / 60)} h ${m % 60} min`
}

/**
 * Cómo va la sala hoy: cuántos esperan, cuántos se atendieron, quién no vino
 * y cuánto tarda, de la llegada a la consulta registrada.
 */
export function TableroFlujo({ panorama, cargando }: Props) {
  const f = panorama?.flujo_hoy
  const e = f?.por_estado
  const enSala = (e?.en_espera ?? 0) + (e?.en_sala ?? 0) + (e?.en_consulta ?? 0)

  return (
    <SectionCard
      title="Hoy en el Dispensario"
      description="Turnos del día. Se actualiza solo cada dos minutos."
    >
      <Stack gap="md">
        <SimpleGrid cols={{ base: 1, xs: 2, md: 4 }} spacing="md">
          <StatCard label="Turnos de hoy" value={f?.total ?? 0} icon={IconUsersGroup} loading={cargando} />
          <StatCard
            label="Esperando"
            value={enSala}
            icon={IconClockHour4}
            loading={cargando}
            tone={enSala >= 5 ? 'warning' : undefined}
            hint={`${e?.en_consulta ?? 0} en consulta`}
          />
          <StatCard label="Atendidos" value={e?.atendido ?? 0} icon={IconUserCheck} loading={cargando} />
          <StatCard
            label="No se presentaron"
            value={e?.no_presentado ?? 0}
            icon={IconUserX}
            loading={cargando}
            hint={`${e?.cancelada ?? 0} cancelados`}
          />
        </SimpleGrid>

        <Group gap="sm">
          <Text size="sm" c="dimmed">De la llegada a la consulta:</Text>
          <StatusBadge>Hoy {minutos(f?.espera_promedio_min)}</StatusBadge>
          <StatusBadge>En el período {minutos(panorama?.espera_periodo_min)}</StatusBadge>
        </Group>
      </Stack>
    </SectionCard>
  )
}
