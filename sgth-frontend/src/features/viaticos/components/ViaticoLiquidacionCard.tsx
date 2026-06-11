'use client'

import {
  Card, Group, Text, Button,
  Divider, Stack, ThemeIcon, Alert,
} from '@mantine/core'
import {
  IconFileInvoice, IconChecks,
} from '@tabler/icons-react'
import { LiquidacionSection } from './LiquidacionSection'
import type { ViaticoConRelaciones, CategoriaFactura } from '@/types/api'
import { useCategoriasFactura } from '../hooks/useViaticos'

interface Props {
  viatico:      ViaticoConRelaciones
  estadoActual: string
  onSuccess:    () => void
}

function fmtMonto(v?: number | string | null): string {
  if (v == null) return '—'
  return `$${Number(v).toFixed(2)}`
}

export function ViaticoLiquidacionCard({
  viatico: d,
  estadoActual,
  onSuccess,
}: Props) {
  const { data: categoriasData = [] } = useCategoriasFactura()

  type FacturaConCategoria = {
    monto?:     number | string | null
    categoria?: { grupo?: string } | null
  }

  const facturas = (d.liquidacion?.detalles_factura ?? []) as FacturaConCategoria[]

  const idsViatico = (categoriasData as CategoriaFactura[])
    .filter(c => c.grupo === 'viatico')
    .map(c => Number(c.id))

  const montoAsignado = Number(d.monto_calculado ?? 0)
  const anticipo      = Number(d.monto_anticipo  ?? 0)
  const monto70       = Math.round(montoAsignado * 0.70 * 100) / 100
  const monto30       = Math.round(montoAsignado * 0.30 * 100) / 100
  const modalidad     = (d.modalidad_anticipo as string) ?? 'sin_anticipo'

  const totalHospAli = facturas
    .filter(f => idsViatico.includes(
      Number((f as { categoria_factura_id?: number })
        .categoria_factura_id ?? 0)
    ))
    .reduce((sum, f) => sum + Number(f.monto ?? 0), 0)

  const totalMovilizacion = facturas
    .filter(f => !idsViatico.includes(
      Number((f as { categoria_factura_id?: number })
        .categoria_factura_id ?? 0)
    ))
    .reduce((sum, f) => sum + Number(f.monto ?? 0), 0)

  const porcentajeHA = monto70 > 0
    ? Math.min(Math.round((totalHospAli / monto70) * 100), 100)
    : 0

  const justificadoCompleto = totalHospAli >= monto70

  const diferenciaDevolver = modalidad === 'sin_anticipo'
    ? 0
    : totalHospAli >= anticipo ? 0
    : Math.round((anticipo - totalHospAli) * 100) / 100

  return (
    <Card withBorder radius="md">
      <Group gap="xs" mb="sm">
        <ThemeIcon color="emerald" variant="light" size="sm">
          <IconFileInvoice size={14} />
        </ThemeIcon>
        <Text fw={600} size="sm">Liquidación</Text>
      </Group>
      <Divider mb="sm" />

      {estadoActual === 'pendiente_liquidacion' ? (
        <LiquidacionSection viatico={d} onSuccess={onSuccess} />
      ) : d.liquidacion ? (
        <Stack gap="xs">
          <Text size="xs" fw={700} c="blue" mb={4}>
            Viático diario — H&A
          </Text>
          <Group justify="space-between">
            <Text size="xs" c="dimmed">Monto asignado</Text>
            <Text size="xs" fw={600}>{fmtMonto(montoAsignado)}</Text>
          </Group>
          <Group justify="space-between">
            <Text size="xs" c="dimmed">70% a justificar (H&A)</Text>
            <Text size="xs" fw={600}>{fmtMonto(monto70)}</Text>
          </Group>
          {anticipo > 0 && (
            <Group justify="space-between">
              <Text size="xs" c="dimmed">Anticipo entregado</Text>
              <Text size="xs" fw={600}>{fmtMonto(anticipo)}</Text>
            </Group>
          )}
          <Group justify="space-between">
            <Text size="xs" c="dimmed">Total H&A presentado</Text>
            <Text size="xs" fw={700}
              c={justificadoCompleto ? 'teal' : 'orange'}>
              {fmtMonto(totalHospAli)} ({porcentajeHA}%)
            </Text>
          </Group>
          <Group justify="space-between">
            <Text size="xs" c="dimmed">30% devengado</Text>
            <Text size="xs" fw={600}>{fmtMonto(monto30)}</Text>
          </Group>
          <Divider my={4} />
          <Group justify="space-between">
            <Text size="xs" fw={600}>A devolver a la institución</Text>
            <Text size="xs" fw={700}
              c={diferenciaDevolver > 0 ? 'red' : 'teal'}>
              {fmtMonto(diferenciaDevolver)}
            </Text>
          </Group>
          {diferenciaDevolver > 0 && (
            <Alert color="red" variant="light" p="xs" mt={4}>
              <Text size="xs">
                Faltan <strong>{fmtMonto(diferenciaDevolver)}</strong>
                {' '}en H&A por justificar.
              </Text>
            </Alert>
          )}
          {justificadoCompleto && (
            <Alert color="teal" variant="light" p="xs" mt={4}>
              <Text size="xs">
                Justificación completa del 70%.
                Devengado: {fmtMonto(monto30)}
              </Text>
            </Alert>
          )}
          {totalMovilizacion > 0 && (
            <>
              <Divider my={6} label="Movilización" labelPosition="left" />
              <Group justify="space-between">
                <Text size="xs" c="dimmed">Total movilización</Text>
                <Text size="xs" fw={600} c="orange">
                  {fmtMonto(totalMovilizacion)}
                </Text>
              </Group>
              <Text size="xs" c="dimmed">
                Rubro independiente — no afecta el viático diario
              </Text>
            </>
          )}
          {(d.liquidacion.actividades?.length ?? 0) > 0 && (
            <Stack gap={4}>
              <Text size="xs" fw={600} c="dimmed">
                ACTIVIDADES REALIZADAS
              </Text>
              {d.liquidacion.actividades!.map((a, i) => (
                <Group key={i} gap="xs">
                  <ThemeIcon size="xs" color="blue"
                    variant="light" radius="xl">
                    <IconChecks size={8} />
                  </ThemeIcon>
                  <Text size="xs">
                    {a.fecha
                      ? new Date(a.fecha).toLocaleDateString('es-EC', {
                          timeZone: 'UTC',
                          day: '2-digit', month: '2-digit',
                        })
                      : '—'}
                    {' — '}{a.lugar}
                  </Text>
                </Group>
              ))}
            </Stack>
          )}
          {(d.liquidacion.detalles_factura?.length ?? 0) > 0 && (
            <Stack gap={4}>
              <Text size="xs" fw={600} c="dimmed">COMPROBANTES</Text>
              {d.liquidacion.detalles_factura!.map((f, i) => (
                <Group key={i} justify="space-between">
                  <Text size="xs" style={{ flex: 1 }}>
                    {f.nombre_proveedor ?? '—'}
                  </Text>
                  <Text size="xs" fw={600} c="orange">
                    ${Number(f.monto ?? 0).toFixed(2)}
                  </Text>
                </Group>
              ))}
            </Stack>
          )}
        </Stack>
      ) : (
        <Text size="sm" c="dimmed">
          Pendiente de registrar la liquidación.
        </Text>
      )}
    </Card>
  )
}
