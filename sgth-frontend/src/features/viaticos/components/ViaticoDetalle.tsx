'use client'

import { useState } from 'react'
import {
  Drawer, Stack, Tabs, Text, Badge,
  Group, Grid, Card, Skeleton, Button,
} from '@mantine/core'
import {
  IconPlane, IconRoute, IconFileInvoice,
  IconCheck, IconCurrencyDollar, IconPlus,
} from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useViatico } from '../hooks/useViaticos'
import { useViaticoMutations } from '../hooks/useViaticoMutations'
import { TramosList } from './TramosList'
import { TramoForm } from './TramoForm'
import { LiquidacionForm } from './LiquidacionForm'
import type { Viatico, EstadoViatico, ViaticoConRelaciones } from '@/types/api'

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

function formatDateTime(f?: string | null): string {
  if (!f) return '—'
  return new Date(f).toLocaleString('es-EC', {
    timeZone: 'UTC',
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

export function ViaticoDetalle({ opened, onClose, viatico }: Props) {
  const { isMobile }  = useMobileBreakpoint()
  const [mostrarTramoForm, setMostrarTramoForm] = useState(false)

  const { data: detalle, isLoading } =
    useViatico(opened ? (viatico?.id ?? null) : null)

  const {
    aprobar,
    entregarAnticipo,
    marcarEnComision,
    marcarPendienteLiquidacion,
    contabilizar,
  } = useViaticoMutations()

  const d = detalle as ViaticoConRelaciones | undefined
  const estadoActual = (d?.estado ?? viatico?.estado ?? '') as string

  const puedeAgregarTramos = [
    'solicitado', 'aprobado'
  ].includes(estadoActual)

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <Text fw={600}>
            {d?.codigo_viatico ?? viatico?.codigo_viatico ?? '...'}
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
      ) : !d ? (
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
                  }[d.zona as string] ?? (d.zona as string)}
                </Text>
              </Grid.Col>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">Total días</Text>
                <Text size="sm" fw={600}>
                  {Number(d.total_dias ?? 0).toFixed(1)} días
                </Text>
              </Grid.Col>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">Salida</Text>
                <Text size="sm">
                  {formatDateTime(d.datetime_salida as string)}
                </Text>
              </Grid.Col>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">Llegada</Text>
                <Text size="sm">
                  {formatDateTime(d.datetime_llegada as string)}
                </Text>
              </Grid.Col>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">Monto calculado</Text>
                <Text size="sm" fw={600} c="emerald">
                  {formatMonto(d.monto_calculado)}
                </Text>
              </Grid.Col>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">Anticipo</Text>
                <Text size="sm" fw={500}>
                  {formatMonto(d.monto_anticipo)}
                </Text>
              </Grid.Col>
              <Grid.Col span={12}>
                <Text size="xs" c="dimmed">Justificación</Text>
                <Text size="sm">
                  {(d.justificacion as string) ?? '—'}
                </Text>
              </Grid.Col>
            </Grid>
          </Card>

          {/* ── Acciones según estado ── */}
          {estadoActual === 'solicitado' && (
            <Button
              color="emerald"
              variant="light"
              leftSection={<IconCheck size={16} />}
              loading={aprobar.isPending}
              onClick={() => {
                aprobar.mutate(d.id)
                onClose()
              }}
              fullWidth
            >
              Aprobar viático
            </Button>
          )}

          {estadoActual === 'aprobado' && (
            <Button
              color="cyan"
              variant="light"
              leftSection={<IconCurrencyDollar size={16} />}
              loading={entregarAnticipo.isPending}
              onClick={() => {
                entregarAnticipo.mutate(d.id)
                onClose()
              }}
              fullWidth
            >
              Entregar anticipo
            </Button>
          )}

          {estadoActual === 'con_anticipo' && (
            <Button
              color="violet"
              variant="light"
              leftSection={<IconPlane size={16} />}
              loading={marcarEnComision.isPending}
              onClick={() => {
                marcarEnComision.mutate(d.id)
                onClose()
              }}
              fullWidth
            >
              Marcar en comisión
            </Button>
          )}

          {estadoActual === 'en_comision' && (
            <Button
              color="yellow"
              variant="light"
              leftSection={<IconFileInvoice size={16} />}
              loading={marcarPendienteLiquidacion.isPending}
              onClick={() => {
                marcarPendienteLiquidacion.mutate(d.id)
                onClose()
              }}
              fullWidth
            >
              Marcar pendiente de liquidación
            </Button>
          )}

          {estadoActual === 'liquidado' && (
            <Button
              color="gray"
              variant="light"
              leftSection={<IconCheck size={16} />}
              loading={contabilizar.isPending}
              onClick={() => {
                contabilizar.mutate(d.id)
                onClose()
              }}
              fullWidth
            >
              Contabilizar liquidación
            </Button>
          )}

          {/* ── Tabs ── */}
          <Tabs defaultValue="itinerario">
            <Tabs.List>
              <Tabs.Tab
                value="itinerario"
                leftSection={<IconRoute size={14} />}
              >
                Itinerario
              </Tabs.Tab>
              {(estadoActual === 'pendiente_liquidacion' ||
                estadoActual === 'liquidado' ||
                estadoActual === 'contabilizado') && (
                <Tabs.Tab
                  value="liquidacion"
                  leftSection={
                    estadoActual === 'pendiente_liquidacion'
                      ? <IconCurrencyDollar size={14} />
                      : <IconFileInvoice size={14} />
                  }
                >
                  {estadoActual === 'pendiente_liquidacion'
                    ? 'Liquidar'
                    : 'Liquidación'}
                </Tabs.Tab>
              )}
            </Tabs.List>

            {/* Tab Itinerario */}
            <Tabs.Panel value="itinerario" pt="md">
              <Stack gap="sm">
                <TramosList
                  viaticoId={d.id}
                  puedeEditar={puedeAgregarTramos}
                />

                {puedeAgregarTramos && (
                  <>
                    <Button
                      size="xs"
                      variant="light"
                      color="blue"
                      leftSection={<IconPlus size={12} />}
                      onClick={() =>
                        setMostrarTramoForm(v => !v)
                      }
                    >
                      {mostrarTramoForm
                        ? 'Cancelar'
                        : 'Agregar tramo'}
                    </Button>

                    {mostrarTramoForm && (
                      <Card withBorder radius="md" p="sm">
                        <TramoForm
                          viaticoId={d.id}
                          onSuccess={() =>
                            setMostrarTramoForm(false)
                          }
                          onCancel={() =>
                            setMostrarTramoForm(false)
                          }
                        />
                      </Card>
                    )}
                  </>
                )}
              </Stack>
            </Tabs.Panel>

            {/* Tab Liquidación */}
            <Tabs.Panel value="liquidacion" pt="md">
              <LiquidacionForm
                viatico={d as Viatico}
                onSuccess={onClose}
              />
            </Tabs.Panel>
          </Tabs>
        </Stack>
      )}
    </Drawer>
  )
}
