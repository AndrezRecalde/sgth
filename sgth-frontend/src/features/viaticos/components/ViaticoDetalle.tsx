'use client'

import {
  Drawer, Stack, Tabs, Text, Badge,
  Group, Grid, Card, Divider,
  Skeleton, Button,
} from '@mantine/core'
import {
  IconPlane, IconMapPin, IconTruck,
  IconFileInvoice, IconUsers, IconCheck,
  IconCurrencyDollar,
} from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useViatico } from '../hooks/useViaticos'
import { useViaticoMutations } from '../hooks/useViaticoMutations'
import { DestinosList } from './DestinosList'
import { TransportesList } from './TransportesList'
import { LiquidacionForm } from './LiquidacionForm'
import type { Viatico, EstadoViatico } from '@/types/api'

interface Props {
  opened:  boolean
  onClose: () => void
  viatico: Viatico | null
}

const ESTADO_COLORS: Record<string, string> = {
  solicitado:            'orange',
  aprobado:              'blue',
  con_anticipo:          'cyan',
  en_comision:           'violet',
  pendiente_liquidacion: 'yellow',
  liquidado:             'emerald',
  contabilizado:         'gray',
}

const ESTADO_LABELS: Record<string, string> = {
  solicitado:            'Solicitado',
  aprobado:              'Aprobado',
  con_anticipo:          'Con anticipo',
  en_comision:           'En comisión',
  pendiente_liquidacion: 'Pend. liquidación',
  liquidado:             'Liquidado',
  contabilizado:         'Contabilizado',
}

function formatMonto(v?: number | string | null): string {
  if (v === null || v === undefined) return '—'
  return `$${Number(v).toFixed(2)}`
}

function formatFecha(f?: string | null): string {
  if (!f) return '—'
  return new Date(f).toLocaleDateString('es-EC', {
    timeZone: 'UTC',
    day: '2-digit', month: '2-digit', year: 'numeric',
  })
}

export function ViaticoDetalle({ opened, onClose, viatico }: Props) {
  const { isMobile }  = useMobileBreakpoint()
  const { data: detalle, isLoading } =
    useViatico(opened ? (viatico?.id ?? null) : null)

  const { aprobar } = useViaticoMutations()

  const estadoActual = (detalle?.estado ??
    viatico?.estado ?? '') as string

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <Text fw={600}>
            {detalle?.codigo_viatico ?? viatico?.codigo_viatico ?? '...'}
          </Text>
          <Badge
            color={ESTADO_COLORS[estadoActual] ?? 'gray'}
            variant="light"
            size="sm"
          >
            {ESTADO_LABELS[estadoActual] ?? estadoActual}
          </Badge>
        </Group>
      }
      position="right"
      size={isMobile ? '100%' : '50rem'}
    >
      {isLoading ? (
        <Stack gap="sm" p="md">
          <Skeleton height={80} />
          <Skeleton height={200} />
          <Skeleton height={200} />
        </Stack>
      ) : !detalle ? (
        <Text c="dimmed" size="sm" p="md">
          No se pudo cargar el detalle.
        </Text>
      ) : (
        <Stack gap="md" p="md">

          {/* ── Resumen ── */}
          <Card withBorder radius="md" p="sm">
            <Grid>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">Zona</Text>
                <Text size="sm" fw={500}>
                  {{
                    dentro_provincia: 'Dentro de la provincia',
                    fuera_provincia:  'Fuera de la provincia',
                    exterior:         'Exterior',
                  }[detalle.zona as string] ?? (detalle.zona as string)}
                </Text>
              </Grid.Col>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">Tipo</Text>
                <Text size="sm" fw={500}>
                  {{
                    con_pernocte: 'Con pernocte',
                    sin_pernocte: 'Sin pernocte',
                  }[detalle.tipo as string] ?? (detalle.tipo as string)}
                </Text>
              </Grid.Col>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">Período</Text>
                <Text size="sm">
                  {formatFecha(detalle.fecha_inicio as string)} –{' '}
                  {formatFecha(detalle.fecha_fin as string)}
                </Text>
              </Grid.Col>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">Monto calculado</Text>
                <Text size="sm" fw={600} c="emerald">
                  {formatMonto(detalle.monto_calculado)}
                </Text>
              </Grid.Col>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">Anticipo</Text>
                <Text size="sm" fw={500}>
                  {formatMonto(detalle.monto_anticipo)}
                </Text>
              </Grid.Col>
              <Grid.Col span={12}>
                <Text size="xs" c="dimmed">Justificación</Text>
                <Text size="sm">
                  {(detalle.justificacion as string) ?? '—'}
                </Text>
              </Grid.Col>
            </Grid>
          </Card>

          {/* ── Acciones rápidas ── */}
          {estadoActual === 'solicitado' && (
            <Button
              color="emerald"
              variant="light"
              leftSection={<IconCheck size={16} />}
              loading={aprobar.isPending}
              onClick={() => {
                aprobar.mutate(detalle.id)
                onClose()
              }}
              fullWidth
            >
              Aprobar viático
            </Button>
          )}

          {/* ── Tabs ── */}
          <Tabs defaultValue="destinos">
            <Tabs.List>
              <Tabs.Tab
                value="destinos"
                leftSection={<IconMapPin size={14} />}
              >
                Destinos
              </Tabs.Tab>
              <Tabs.Tab
                value="transportes"
                leftSection={<IconTruck size={14} />}
              >
                Transporte
              </Tabs.Tab>
              {estadoActual === 'pendiente_liquidacion' && (
                <Tabs.Tab
                  value="liquidacion"
                  leftSection={<IconCurrencyDollar size={14} />}
                >
                  Liquidar
                </Tabs.Tab>
              )}
              {(estadoActual === 'liquidado' ||
                estadoActual === 'contabilizado') && (
                <Tabs.Tab
                  value="liquidacion"
                  leftSection={<IconFileInvoice size={14} />}
                >
                  Liquidación
                </Tabs.Tab>
              )}
            </Tabs.List>

            <Tabs.Panel value="destinos" pt="md">
              <DestinosList viaticoId={detalle.id} />
            </Tabs.Panel>

            <Tabs.Panel value="transportes" pt="md">
              <TransportesList viaticoId={detalle.id} />
            </Tabs.Panel>

            <Tabs.Panel value="liquidacion" pt="md">
              <LiquidacionForm
                viatico={detalle as Viatico}
                onSuccess={onClose}
              />
            </Tabs.Panel>
          </Tabs>
        </Stack>
      )}
    </Drawer>
  )
}
