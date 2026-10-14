'use client'

import Link from 'next/link'
import { Anchor, Group, SimpleGrid, Stack, Text } from '@mantine/core'
import { IconAlarm, IconClockExclamation, IconInbox, IconUserCheck } from '@tabler/icons-react'
import { SectionCard, StatCard, StatusBadge } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { ROUTES } from '@/config/routes'
import { formatFechaHora } from '@/lib/fecha'
import { ETIQUETA_PRIORIDAD, SLA_LABELS, TONO_PRIORIDAD } from '@/features/helpdesk/constants/estados'
import { ESTADO_EN_CURSO } from '../constants/etiquetas'
import type { EstadoEnCurso, TableroTickets as Datos } from '../services/tableroService'
import { CifrasDelMes } from './CifrasDelMes'
import { Reparto } from './Reparto'

/**
 * La mesa de ayuda en el tablero: lo que espera a TIC, la cola de quien mira,
 * las cifras del mes y en qué categorías se acumula el trabajo.
 */
export function TableroTickets({ datos }: { datos: Datos }) {
  const estados = (Object.keys(ESTADO_EN_CURSO) as EstadoEnCurso[]).map((e) => ({
    clave: e, etiqueta: ESTADO_EN_CURSO[e].etiqueta, total: datos.por_estado[e], tono: ESTADO_EN_CURSO[e].tono,
  }))

  return (
    <Stack gap="md">
      <SimpleGrid cols={{ base: 1, xs: 2, lg: 4 }} spacing="md">
        <StatCard label="Sin asignar" value={datos.sin_asignar} icon={IconInbox} hint="Esperan que alguien los tome" />
        <StatCard label="En su cola" value={datos.mios} icon={IconUserCheck} hint="Asignados a usted, en curso" />
        <StatCard label="Por vencer" value={datos.por_vencer} icon={IconAlarm} tone={datos.por_vencer ? 'warning' : undefined}
          hint="Queda la cuarta parte del plazo" />
        <StatCard label="Vencidos" value={datos.vencidos} icon={IconClockExclamation} tone={datos.vencidos ? 'danger' : undefined}
          hint="Fuera del plazo de resolución" />
      </SimpleGrid>

      <CifrasDelMes mes={datos.mes} alcance={datos.alcance} porConfirmar={datos.por_confirmar} />

      <SimpleGrid cols={{ base: 1, lg: 3 }} spacing="md">
        <SectionCard title="Su cola" description="Lo que tiene a su nombre, primero lo que vence antes.">
          {datos.mi_cola.length === 0 ? (
            <Text size="sm" c="dimmed">No tiene tickets en curso a su nombre.</Text>
          ) : (
            <Stack gap="sm">
              {datos.mi_cola.map((t) => (
                <Stack key={t.id} gap={2}>
                  <Group gap="xs" wrap="nowrap" justify="space-between">
                    <Anchor size="sm" fw={600} component={Link} href={ROUTES.TIC.TICKET(t.id)}>{t.folio}</Anchor>
                    <StatusBadge size="xs" tone={TONO_PRIORIDAD[t.prioridad]}>{ETIQUETA_PRIORIDAD[t.prioridad]}</StatusBadge>
                  </Group>
                  <Text size="xs" lineClamp={1}>{t.asunto}</Text>
                  {t.sla && (
                    <Text size="xs" c={t.sla === 'vencido' ? SEMANTIC_COLOR.danger : 'dimmed'}>
                      {SLA_LABELS[t.sla].etiqueta}{t.vence_en && t.sla !== 'pausado' ? ` · vence ${formatFechaHora(t.vence_en)}` : ''}
                    </Text>
                  )}
                </Stack>
              ))}
            </Stack>
          )}
        </SectionCard>

        <SectionCard title="En curso por estado" description={datos.alcance === 'todos' ? 'Toda la mesa de ayuda.' : 'Los suyos y los sin asignar.'}>
          <Reparto filas={estados} vacio="No hay tickets en curso." />
        </SectionCard>

        <SectionCard title="En curso por categoría" description="Las seis con más tickets abiertos.">
          <Reparto filas={datos.por_categoria.map((c) => ({ clave: c.categoria, etiqueta: c.categoria, total: c.total }))}
            vacio="No hay tickets en curso." />
        </SectionCard>
      </SimpleGrid>
    </Stack>
  )
}
