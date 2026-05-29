'use client'

import {
  Modal, Stack, Text, Group, Badge,
  Table, Divider, Skeleton,
} from '@mantine/core'
import { useQuery } from '@tanstack/react-query'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { nominaService } from '../services/nominaService'
import type { Nomina, ServidorConRelaciones } from '@/types/api'

interface Props {
  opened:    boolean
  onClose:   () => void
  nomina:    Nomina | null
  servidor:  ServidorConRelaciones | null
}

function formatMonto(v?: number | string | null): string {
  if (v === null || v === undefined) return '—'
  return `$${Number(v).toLocaleString('es-EC', {
    minimumFractionDigits: 2, maximumFractionDigits: 2,
  })}`
}

export function RolPagoModal({ opened, onClose, nomina, servidor }: Props) {
  const { isMobile } = useMobileBreakpoint()

  const { data: rol, isLoading } = useQuery({
    queryKey: ['rol-pago', nomina?.id, servidor?.id],
    queryFn:  () => nominaService.rolPago(nomina!.id, Number(servidor!.id)),
    enabled:  !!nomina && !!servidor,
    staleTime: 0,
  })

  const detalles = rol?.nomina?.detalles ?? []
  const ingresos   = detalles.filter(d => d.concepto?.tipo === 'ingreso')
  const descuentos = detalles.filter(d => d.concepto?.tipo === 'descuento')

  const nombreServidor = servidor
    ? [servidor.apellido, servidor.segundo_apellido,
       servidor.nombre, servidor.segundo_nombre]
        .filter(Boolean).join(' ')
    : '—'

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={`Rol de pago — ${nomina?.periodo ?? ''}`}
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      {isLoading ? (
        <Stack gap="sm">
          <Skeleton height={24} />
          <Skeleton height={200} />
        </Stack>
      ) : (
        <Stack gap="md">
          <Group justify="space-between">
            <div>
              <Text size="sm" fw={600}>{nombreServidor}</Text>
              <Text size="xs" c="dimmed">
                {servidor?.cedula ?? '—'}
              </Text>
            </div>
            <Badge color="emerald" variant="light">
              {nomina?.periodo}
            </Badge>
          </Group>

          <Divider label="Ingresos" labelPosition="left" />
          <Table striped highlightOnHover withTableBorder>
            <Table.Thead>
              <Table.Tr>
                <Table.Th>Concepto</Table.Th>
                <Table.Th ta="right">Valor</Table.Th>
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {ingresos.map(d => (
                <Table.Tr key={d.id}>
                  <Table.Td>
                    <Text size="sm">{d.concepto?.nombre ?? '—'}</Text>
                  </Table.Td>
                  <Table.Td ta="right">
                    <Text size="sm" ff="monospace" c="emerald">
                      {formatMonto(d.valor)}
                    </Text>
                  </Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>

          <Divider label="Descuentos" labelPosition="left" />
          <Table striped highlightOnHover withTableBorder>
            <Table.Thead>
              <Table.Tr>
                <Table.Th>Concepto</Table.Th>
                <Table.Th ta="right">Valor</Table.Th>
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {descuentos.map(d => (
                <Table.Tr key={d.id}>
                  <Table.Td>
                    <Text size="sm">{d.concepto?.nombre ?? '—'}</Text>
                  </Table.Td>
                  <Table.Td ta="right">
                    <Text size="sm" ff="monospace" c="red">
                      {formatMonto(d.valor)}
                    </Text>
                  </Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>

          <Divider />
          <Group justify="space-between">
            <Text size="sm" c="dimmed">Total ingresos</Text>
            <Text size="sm" fw={500} c="emerald">
              {formatMonto(rol?.total_ingresos)}
            </Text>
          </Group>
          <Group justify="space-between">
            <Text size="sm" c="dimmed">Total descuentos</Text>
            <Text size="sm" fw={500} c="red">
              {formatMonto(rol?.total_descuentos)}
            </Text>
          </Group>
          <Divider />
          <Group justify="space-between">
            <Text fw={700}>NETO A PAGAR</Text>
            <Text fw={700} size="lg">
              {formatMonto(rol?.total_neto)}
            </Text>
          </Group>
        </Stack>
      )}
    </Modal>
  )
}
