'use client'

import { Group, Progress, SimpleGrid, Stack, Text } from '@mantine/core'
import {
  IconCalendarOff, IconHeartbeat, IconInbox, IconLogout, IconPlayerPlay,
} from '@tabler/icons-react'
import { SectionCard, StatCard, StatusBadge } from '@/components/ui'
import type { TableroSaludOcupacional } from '../services/tableroSaludOcupacionalService'
import { etiquetaTipoEvento } from '../services/solicitudCertificacionService'

interface Props {
  datos?:   TableroSaludOcupacional
  cargando: boolean
}

const TRAMOS: { clave: keyof TableroSaludOcupacional['antiguedad']; etiqueta: string }[] = [
  { clave: '0_3',    etiqueta: '0 a 3 días' },
  { clave: '4_7',    etiqueta: '4 a 7 días' },
  { clave: '8_15',   etiqueta: '8 a 15 días' },
  { clave: 'mas_15', etiqueta: 'Más de 15 días' },
]

/**
 * La bandeja en cifras: lo que hay por atender hoy y cuánto lleva esperando.
 *
 * El tono va solo cuando la cifra tiene lectura (regla 06): sin vencidas, la
 * tarjeta no se pinta de rojo.
 */
export function TableroSsoBandeja({ datos, cargando }: Props) {
  const b = datos?.bandeja
  const abiertas = (b?.pendientes ?? 0) + (b?.en_proceso ?? 0)
  const porTipo = Object.entries(datos?.abiertas_por_tipo ?? {}).sort((x, y) => y[1] - x[1])

  return (
    <Stack gap="md">
      <SimpleGrid cols={{ base: 1, xs: 2, md: 5 }} spacing="md">
        <StatCard label="Por atender" value={abiertas} icon={IconInbox} loading={cargando}
          hint={`${b?.pendientes ?? 0} pendientes · ${b?.en_proceso ?? 0} en curso`} />
        <StatCard label="Vencidas" value={b?.vencidas ?? 0} icon={IconCalendarOff} loading={cargando}
          tone={b?.vencidas ? 'danger' : undefined} hint="Pasada la fecha límite" />
        <StatCard label="Esperan triaje" value={b?.sin_triaje ?? 0} icon={IconHeartbeat} loading={cargando}
          tone={b?.sin_triaje ? 'warning' : undefined} hint="Enfermería aún no toma los signos" />
        <StatCard label="En curso" value={b?.en_proceso ?? 0} icon={IconPlayerPlay} loading={cargando}
          hint="Ficha FEMO abierta" />
        <StatCard label="Retiros por evaluar" value={b?.retiros ?? 0} icon={IconLogout} loading={cargando}
          tone={b?.retiros ? 'warning' : undefined} hint="Llegan solos del cese" />
      </SimpleGrid>

      <SimpleGrid cols={{ base: 1, md: 2 }} spacing="md">
        <SectionCard title="Antigüedad de lo abierto" description="Días desde que Talento Humano pidió la evaluación">
          <Stack gap="sm">
            {TRAMOS.map(({ clave, etiqueta }) => {
              const n = datos?.antiguedad[clave] ?? 0
              return (
                <Stack key={clave} gap={4}>
                  <Group justify="space-between">
                    <Text size="sm">{etiqueta}</Text>
                    <Text size="sm" fw={600}>{n}</Text>
                  </Group>
                  <Progress
                    value={abiertas ? (n / abiertas) * 100 : 0}
                    color={clave === 'mas_15' && n > 0 ? 'red' : undefined}
                    aria-label={`${etiqueta}: ${n}`}
                  />
                </Stack>
              )
            })}
          </Stack>
        </SectionCard>

        <SectionCard title="Abiertas por tipo de evaluación">
          {porTipo.length === 0 ? (
            <Text size="sm" c="dimmed">No hay evaluaciones abiertas.</Text>
          ) : (
            <Stack gap="xs">
              {porTipo.map(([tipo, n]) => (
                <Group key={tipo} justify="space-between">
                  <StatusBadge>{etiquetaTipoEvento(tipo)}</StatusBadge>
                  <Text size="sm" fw={600}>{n}</Text>
                </Group>
              ))}
            </Stack>
          )}
        </SectionCard>
      </SimpleGrid>
    </Stack>
  )
}
