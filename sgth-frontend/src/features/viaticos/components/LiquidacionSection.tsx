'use client'

import { useState } from 'react'
import {
  Stack, Card, Text, Group, Button,
  Badge, Divider, Alert, ThemeIcon,
  Grid,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconClipboardList, IconFileInvoice,
  IconCheck, IconAlertCircle, IconPencil,
  IconCircleCheck,
} from '@tabler/icons-react'
import { useViaticoMutations } from '../hooks/useViaticoMutations'
import { ActividadesModal } from './ActividadesModal'
import { FacturasModal } from './FacturasModal'
import type { ActividadData } from './ActividadesModal'
import type { FacturaData } from './FacturasModal'
import type { Viatico } from '@/types/api'

interface Props {
  viatico:   Viatico
  onSuccess: () => void
}

function fmtMonto(v?: number | string | null): string {
  if (v == null) return '—'
  return `$${Number(v).toFixed(2)}`
}

export function LiquidacionSection({ viatico, onSuccess }: Props) {
  const [actividades, setActividades] =
    useState<ActividadData[]>([])
  const [facturas, setFacturas] =
    useState<FacturaData[]>([])

  const [
    actModalAbierto,
    { open: abrirAct, close: cerrarAct },
  ] = useDisclosure(false)

  const [
    factModalAbierto,
    { open: abrirFact, close: cerrarFact },
  ] = useDisclosure(false)

  const { liquidar } = useViaticoMutations()

  const anticipo     = Number(viatico.monto_anticipo ?? 0)
  const totalFacturas = facturas.reduce(
    (sum, f) => sum + (Number(f.monto) || 0), 0
  )
  const diferencia   = anticipo - totalFacturas

  const puedeRegistrar =
    actividades.length > 0 && facturas.length > 0

  const handleRegistrar = async () => {
    await liquidar.mutateAsync({
      viaticoId: viatico.id,
      data: {
        fecha_retorno: (() => {
          const d = new Date(
            viatico.datetime_llegada as string
          )
          return d.toISOString().slice(0, 10)
        })(),
        actividades,
        facturas,
      },
    })
    onSuccess()
  }

  return (
    <Stack gap="md">

      {/* Resumen rápido si ya hay datos */}
      {(actividades.length > 0 || facturas.length > 0) && (
        <Card withBorder radius="md" p="sm" bg="gray.0">
          <Group justify="space-between">
            <Text size="sm" c="dimmed">Anticipo:</Text>
            <Text size="sm" fw={600}>
              {fmtMonto(anticipo)}
            </Text>
          </Group>
          <Group justify="space-between">
            <Text size="sm" c="dimmed">
              Total comprobantes:
            </Text>
            <Text size="sm" fw={600} c="blue">
              {fmtMonto(totalFacturas)}
            </Text>
          </Group>
          <Divider my={4} />
          <Group justify="space-between">
            <Text size="sm" fw={600}>
              {diferencia >= 0
                ? 'A devolver:'
                : 'A cobrar:'}
            </Text>
            <Text
              size="sm"
              fw={700}
              c={diferencia >= 0 ? 'orange' : 'emerald'}
            >
              {fmtMonto(Math.abs(diferencia))}
            </Text>
          </Group>
        </Card>
      )}

      <Grid>

        {/* Sección actividades */}
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Card withBorder radius="md" h="100%">
            <Group justify="space-between" mb="sm">
              <Group gap="xs">
                <ThemeIcon
                  color="blue" variant="light" size="sm"
                >
                  <IconClipboardList size={14} />
                </ThemeIcon>
                <Text fw={600} size="sm">
                  Informe de actividades
                </Text>
              </Group>
              {actividades.length > 0 && (
                <Badge
                  color="blue"
                  variant="light"
                  size="sm"
                >
                  {actividades.length}{' '}
                  {actividades.length === 1
                    ? 'actividad'
                    : 'actividades'}
                </Badge>
              )}
            </Group>
            <Divider mb="sm" />

            {actividades.length === 0 ? (
              <Stack gap="xs" align="center" py="md">
                <Alert
                  icon={<IconAlertCircle size={14} />}
                  color="orange"
                  variant="light"
                  w="100%"
                >
                  <Text size="xs">
                    Debe registrar las actividades
                    realizadas durante la comisión.
                  </Text>
                </Alert>
                <Button
                  color="blue"
                  variant="light"
                  size="sm"
                  leftSection={
                    <IconClipboardList size={14} />
                  }
                  onClick={abrirAct}
                  fullWidth
                >
                  Registrar actividades
                </Button>
              </Stack>
            ) : (
              <Stack gap="xs">
                {actividades.map((a, i) => (
                  <Group key={i} gap="xs">
                    <IconCircleCheck
                      size={14}
                      color="var(--mantine-color-emerald-6)"
                    />
                    <Text size="xs">
                      {a.fecha
                        ? new Date(a.fecha)
                            .toLocaleDateString('es-EC', {
                              timeZone: 'UTC',
                              day: '2-digit',
                              month: '2-digit',
                            })
                        : '—'}
                      {' '}— {a.lugar}
                    </Text>
                  </Group>
                ))}
                <Button
                  size="xs"
                  variant="subtle"
                  color="blue"
                  leftSection={<IconPencil size={12} />}
                  onClick={abrirAct}
                >
                  Editar actividades
                </Button>
              </Stack>
            )}
          </Card>
        </Grid.Col>

        {/* Sección facturas */}
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Card withBorder radius="md" h="100%">
            <Group justify="space-between" mb="sm">
              <Group gap="xs">
                <ThemeIcon
                  color="orange" variant="light" size="sm"
                >
                  <IconFileInvoice size={14} />
                </ThemeIcon>
                <Text fw={600} size="sm">
                  Facturas de respaldo
                </Text>
              </Group>
              {facturas.length > 0 && (
                <Badge
                  color="orange"
                  variant="light"
                  size="sm"
                >
                  {facturas.length}{' '}
                  {facturas.length === 1
                    ? 'comprobante'
                    : 'comprobantes'}
                </Badge>
              )}
            </Group>
            <Divider mb="sm" />

            {facturas.length === 0 ? (
              <Stack gap="xs" align="center" py="md">
                <Alert
                  icon={<IconAlertCircle size={14} />}
                  color="orange"
                  variant="light"
                  w="100%"
                >
                  <Text size="xs">
                    Debe adjuntar los comprobantes
                    de los gastos realizados.
                  </Text>
                </Alert>
                <Button
                  color="orange"
                  variant="light"
                  size="sm"
                  leftSection={<IconFileInvoice size={14} />}
                  onClick={abrirFact}
                  fullWidth
                >
                  Registrar comprobantes
                </Button>
              </Stack>
            ) : (
              <Stack gap="xs">
                {facturas.map((f, i) => (
                  <Group key={i} gap="xs">
                    <IconCircleCheck
                      size={14}
                      color="var(--mantine-color-emerald-6)"
                    />
                    <Text size="xs">
                      {f.nombre_proveedor} —{' '}
                      <strong>
                        ${Number(f.monto).toFixed(2)}
                      </strong>
                    </Text>
                  </Group>
                ))}
                <Button
                  size="xs"
                  variant="subtle"
                  color="orange"
                  leftSection={<IconPencil size={12} />}
                  onClick={abrirFact}
                >
                  Editar comprobantes
                </Button>
              </Stack>
            )}
          </Card>
        </Grid.Col>
      </Grid>

      {/* Validación y botón registrar */}
      {!puedeRegistrar && (
        <Alert
          icon={<IconAlertCircle size={14} />}
          color="gray"
          variant="light"
        >
          <Text size="xs">
            Para registrar la liquidación debe completar
            tanto el <strong>informe de actividades</strong>
            {' '}como las <strong>facturas de respaldo</strong>.
          </Text>
        </Alert>
      )}

      <Button
        color="emerald"
        size="md"
        disabled={!puedeRegistrar}
        loading={liquidar.isPending}
        leftSection={<IconCheck size={16} />}
        onClick={handleRegistrar}
        fullWidth
      >
        Registrar liquidación
      </Button>

      {/* Modales */}
      <ActividadesModal
        opened={actModalAbierto}
        onClose={cerrarAct}
        viatico={viatico}
        onGuardar={setActividades}
        valorInicial={actividades}
      />

      <FacturasModal
        opened={factModalAbierto}
        onClose={cerrarFact}
        viatico={viatico}
        onGuardar={setFacturas}
        valorInicial={facturas}
      />
    </Stack>
  )
}
