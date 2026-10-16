'use client'

import Link from 'next/link'
import { Anchor, Group, SimpleGrid, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconTruckDelivery } from '@tabler/icons-react'
import { SectionCard, StatCard } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import type { TableroConsumibles as Datos } from '../services/tableroService'

/**
 * Los consumibles en el tablero (5.4d): lo que guarda TIC y bajó del mínimo,
 * lo que más falta primero, y lo entregado en el mes. El aviso de stock va
 * solo aquí [P6].
 */
export function TableroConsumibles({ datos }: { datos: Datos }) {
  return (
    <SimpleGrid cols={{ base: 1, lg: 2 }} spacing="md">
      <Stack gap="md">
        <StatCard label="Bajo el mínimo" value={datos.bajo_minimo} icon={IconAlertTriangle} tone={datos.bajo_minimo ? 'warning' : undefined}
          hint="De lo que guarda TIC" />
        <StatCard label="Entregado en el mes" value={datos.entregas_mes} icon={IconTruckDelivery} hint="Unidades, de TIC y de Bodega" />
      </Stack>
      <SectionCard title="Por reponer" description="Lo que guarda TIC y bajó del mínimo, lo que más falta primero.">
        {datos.lista.length === 0 ? (
          <Text size="sm" c="dimmed">Nada bajo el mínimo.</Text>
        ) : (
          <Stack gap="sm">
            {datos.lista.map((c) => (
              <Group key={c.id} justify="space-between" wrap="nowrap" gap="xs">
                <Anchor size="sm" component={Link} href={ROUTES.TIC.CONSUMIBLE(c.id)} lineClamp={1}>{c.descripcion}</Anchor>
                <Text size="sm" fw={600} style={{ whiteSpace: 'nowrap' }}>{c.saldo} de {c.stock_minimo}</Text>
              </Group>
            ))}
          </Stack>
        )}
      </SectionCard>
    </SimpleGrid>
  )
}
