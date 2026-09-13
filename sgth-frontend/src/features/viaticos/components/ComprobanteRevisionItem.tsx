'use client'

import { Button, Group, List, Paper, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconCheck, IconMessageExclamation } from '@tabler/icons-react'
import { formatFecha } from '@/lib/fecha'
import { REVISION_LABELS, TONO_REVISION, estadoRevision } from '../utils/revisionComprobantes'
import type { ComprobanteRevisado } from '@/types/api'
import { StatusBadge } from '@/components/ui'

interface Props {
  factura: ComprobanteRevisado
  /** Sin esto, se muestra la revisión sin poder cambiarla. */
  onAceptar?: () => void
  onObservar?: () => void
  cargando?: boolean
}

/** Un comprobante con sus alertas automáticas y la decisión de Financiero. */
export function ComprobanteRevisionItem({ factura: f, onAceptar, onObservar, cargando }: Props) {
  const estado = estadoRevision(f)
  const alertas = f.alertas ?? []

  return (
    <Paper withBorder radius="md" p="sm">
      <Stack gap={6}>
        <Group justify="space-between" wrap="nowrap" align="flex-start">
          <Stack gap={0} style={{ minWidth: 0 }}>
            <Text size="sm" fw={600}>{f.nombre_proveedor}</Text>
            <Text size="xs" c="dimmed">
              {[
                f.categoria?.nombre,
                `${f.tipo_comprobante ?? 'comprobante'} ${f.numero_factura ?? f.numero_ticket ?? ''}`.trim(),
                f.ruc_proveedor ? `RUC ${f.ruc_proveedor}` : null,
                f.fecha_factura ? formatFecha(f.fecha_factura) : null,
              ].filter(Boolean).join(' · ')}
            </Text>
          </Stack>
          <Stack gap={4} align="flex-end">
            <Text size="sm" fw={700} ff="monospace">${Number(f.monto ?? 0).toFixed(2)}</Text>
            <StatusBadge tone={TONO_REVISION[estado]}>{REVISION_LABELS[estado]}</StatusBadge>
          </Stack>
        </Group>

        {alertas.length > 0 && (
          <List
            size="xs"
            spacing={2}
            icon={<IconAlertTriangle size={12} color="var(--mantine-color-amber-7)" />}
          >
            {alertas.map((a) => (
              <List.Item key={a.codigo}>{a.mensaje}</List.Item>
            ))}
          </List>
        )}

        {estado === 'observada' && f.observacion_revision && (
          <Text size="xs" c="red">Observación: {f.observacion_revision}</Text>
        )}

        {(onAceptar || onObservar) && (
          <Group gap="xs" justify="flex-end">
            {onObservar && (
              <Button size="xs" variant="subtle" color="red" leftSection={<IconMessageExclamation size={14} />}
                onClick={onObservar} disabled={cargando}>
                Observar
              </Button>
            )}
            {onAceptar && estado !== 'aceptada' && (
              <Button size="xs" variant="light" color="emerald" leftSection={<IconCheck size={14} />}
                onClick={onAceptar} loading={cargando}>
                Aceptar
              </Button>
            )}
          </Group>
        )}
      </Stack>
    </Paper>
  )
}
