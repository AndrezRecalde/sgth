'use client'

import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { Alert, Anchor, Button, Group, Skeleton, Stack, Text } from '@mantine/core'
import { IconTruckDelivery } from '@tabler/icons-react'
import { SectionCard, SgthTable } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { formatFecha } from '@/lib/fecha'
import { plural } from '@/lib/plural'
import { useConsumiblesDelEquipo } from '../hooks/useConsumibles'
import { columnasCompatiblesDelEquipo } from './consumibles.columns'

interface Props {
  bienId: number
  /** Dado de baja: ya no recibe consumibles. */
  deBaja: boolean
}

/**
 * La sección «Consumibles» de la ficha del equipo (fase 5.4): los compatibles
 * con su saldo, las últimas entregas y cuántos días pasaron desde la última,
 * para ver cada cuánto pide tóner. Lo que se anotaba antes en el campo de
 * texto de la ficha se sigue mostrando [P11].
 */
export function ConsumiblesDelEquipo({ bienId, deBaja }: Props) {
  const router = useRouter()
  const { hasPermiso } = useAuth()
  const { data, isLoading } = useConsumiblesDelEquipo(bienId)

  if (isLoading) return <Skeleton height={240} radius="lg" />
  if (!data) return null

  const entrega = hasPermiso('gestionar-inventario-ti') && !deBaja

  return (
    <Stack gap="lg">
      {data.consumible_anterior && (
        <Alert color={SEMANTIC_COLOR.neutral} variant="light" title="Lo que se anotaba antes en la ficha">
          {data.consumible_anterior}
        </Alert>
      )}

      <SectionCard
        title="Compatibles"
        description="Los consumibles del catálogo marcados para el modelo de este equipo."
        actions={entrega ? (
          <Button variant="light" size="xs" leftSection={<IconTruckDelivery size={14} />} onClick={() => router.push(ROUTES.TIC.CONSUMIBLES_ENTREGAR(bienId))}>
            Entregar
          </Button>
        ) : undefined}
      >
        <SgthTable records={data.compatibles} minHeight={0}
          onRowClick={({ record }) => router.push(ROUTES.TIC.CONSUMIBLE(record.id))}
          noRecordsText="Ningún consumible del catálogo está marcado para este modelo."
          columns={columnasCompatiblesDelEquipo} />
      </SectionCard>

      <SectionCard
        title="Últimas entregas"
        description={data.ultima_entrega
          ? `La última, el ${formatFecha(data.ultima_entrega)}${data.dias_desde_ultima ? `: hace ${plural(data.dias_desde_ultima, 'día', 'días')}` : ', hoy'}.`
          : 'Todavía no se le ha entregado nada.'}
      >
        {data.entregas.length > 0 && (
          <Stack gap="xs">
            {data.entregas.map((e) => (
              <Group key={e.id} justify="space-between" wrap="nowrap">
                <Text size="sm">
                  <Anchor size="sm" component={Link} href={ROUTES.TIC.CONSUMIBLE(e.consumible_id)}>{e.consumible}</Anchor>
                  {' · '}{e.cantidad}{e.retira ? ` · ${e.retira}` : ''}
                </Text>
                <Text size="sm" c="dimmed">{formatFecha(e.fecha)}</Text>
              </Group>
            ))}
          </Stack>
        )}
      </SectionCard>
    </Stack>
  )
}
