'use client'

import {
  Drawer, Stack, Group, Text, Badge,
  ThemeIcon, Table, Divider,
} from '@mantine/core'
import {
  IconShoppingCart, IconFileText,
} from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import type { Adquisicion } from '../services/adquisicionService'

interface Props {
  opened:      boolean
  onClose:     () => void
  adquisicion: Adquisicion | null
}

function formatFecha(fecha?: string | null): string {
  if (!fecha) return '—'
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}

export function DetalleAdquisicionDrawer({
  opened, onClose, adquisicion,
}: Props) {
  const { isMobile } = useMobileBreakpoint()

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon
            color={adquisicion?.tipo === 'donacion' ? 'violet' : 'blue'}
            variant="light"
            size="md"
            radius="md"
          >
            <IconShoppingCart size={16} />
          </ThemeIcon>
          <Stack gap={0}>
            <Text fw={700} size="sm">
              Detalle de adquisición
            </Text>
            <Text size="xs" c="dimmed" ff="monospace">
              {adquisicion?.folio}
            </Text>
          </Stack>
        </Group>
      }
      position="right"
      size={isMobile ? '100%' : 580}
      padding="lg"
    >
      {adquisicion && (
        <Stack gap="md">
          <Stack gap={4}>
            <Group justify="space-between">
              <Text size="xs" c="dimmed">Tipo</Text>
              <Badge
                size="sm"
                variant="light"
                color={adquisicion.tipo === 'donacion' ? 'violet' : 'blue'}
              >
                {adquisicion.tipo === 'donacion' ? 'Donación' : 'Compra'}
              </Badge>
            </Group>
            <Group justify="space-between">
              <Text size="xs" c="dimmed">N° documento</Text>
              <Text size="sm" ff="monospace">
                {adquisicion.numero_documento}
              </Text>
            </Group>
            <Group justify="space-between">
              <Text size="xs" c="dimmed">Proveedor / Donante</Text>
              <Text size="sm">{adquisicion.proveedor_o_donante}</Text>
            </Group>
            <Group justify="space-between">
              <Text size="xs" c="dimmed">Fecha</Text>
              <Text size="sm">
                {formatFecha(adquisicion.fecha_adquisicion)}
              </Text>
            </Group>
            <Group justify="space-between">
              <Text size="xs" c="dimmed">Registrado por</Text>
              <Text size="sm">
                {adquisicion.registrador?.nombre_completo
                  ?? adquisicion.registrador?.usuario_ti ?? '—'}
              </Text>
            </Group>
            {adquisicion.observaciones && (
              <Stack gap={2}>
                <Text size="xs" c="dimmed">Observaciones</Text>
                <Text size="sm">{adquisicion.observaciones}</Text>
              </Stack>
            )}
            <Group justify="space-between">
              <Text size="xs" c="dimmed">Documento de respaldo</Text>
              <Badge
                size="sm"
                variant="dot"
                color={adquisicion.documento_respaldo ? 'emerald' : 'gray'}
                leftSection={<IconFileText size={12} />}
              >
                {adquisicion.documento_respaldo ? 'Adjunto' : 'Pendiente'}
              </Badge>
            </Group>
          </Stack>

          <Divider
            label={
              <Text size="xs" fw={600} tt="uppercase" c="dimmed">
                Medicamentos ({adquisicion.items?.length ?? 0} ítems)
              </Text>
            }
            labelPosition="left"
          />

          <Table withTableBorder withColumnBorders>
            <Table.Thead>
              <Table.Tr>
                <Table.Th>Medicina</Table.Th>
                <Table.Th w={80}>Cantidad</Table.Th>
                <Table.Th w={100}>Lote</Table.Th>
                <Table.Th w={110}>Caduca</Table.Th>
                <Table.Th w={90}>P. Unit.</Table.Th>
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {(adquisicion.items ?? []).map((item) => (
                <Table.Tr key={item.id}>
                  <Table.Td>
                    <Text size="sm" fw={500}>
                      {item.medicina?.nombre ?? '—'}
                    </Text>
                    <Text size="xs" c="dimmed">
                      {item.medicina?.concentracion ?? ''}
                    </Text>
                  </Table.Td>
                  <Table.Td>
                    <Text size="sm" ta="center">
                      {item.cantidad}
                    </Text>
                  </Table.Td>
                  <Table.Td>
                    <Text size="xs" ff="monospace">
                      {item.lote ?? '—'}
                    </Text>
                  </Table.Td>
                  <Table.Td>
                    <Text size="xs">
                      {formatFecha(item.fecha_caducidad)}
                    </Text>
                  </Table.Td>
                  <Table.Td>
                    <Text size="sm">
                      {item.precio_unitario
                        ? `$${Number(item.precio_unitario).toFixed(2)}`
                        : '—'}
                    </Text>
                  </Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Stack>
      )}
    </Drawer>
  )
}
