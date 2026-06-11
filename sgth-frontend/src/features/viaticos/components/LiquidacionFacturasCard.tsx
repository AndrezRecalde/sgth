'use client'

import {
  Card, Group, Text, Badge, Button,
  Divider, Stack, Alert, ThemeIcon,
} from '@mantine/core'
import {
  IconFileInvoice, IconAlertCircle,
  IconPencil, IconCircleCheck,
} from '@tabler/icons-react'
import type { FacturaData } from './FacturasModal'
import type { CategoriaFactura } from '@/types/api'

interface Props {
  facturas:         FacturaData[]
  categorias:       CategoriaFactura[]
  onRegistrar:      () => void
  onEditar:         () => void
}

export function LiquidacionFacturasCard({
  facturas,
  categorias,
  onRegistrar,
  onEditar,
}: Props) {
  const categoriaOptions = categorias.map(c => ({
    value: String(c.id),
    label: c.nombre ?? '',
  }))

  return (
    <Card withBorder radius="md" h="100%">
      <Group justify="space-between" mb="sm">
        <Group gap="xs">
          <ThemeIcon color="orange" variant="light" size="sm">
            <IconFileInvoice size={14} />
          </ThemeIcon>
          <Text fw={600} size="sm">Facturas de respaldo</Text>
        </Group>
        {facturas.length > 0 && (
          <Badge color="orange" variant="light" size="sm">
            {facturas.length}{' '}
            {facturas.length === 1 ? 'comprobante' : 'comprobantes'}
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
              Debe adjuntar los comprobantes de los gastos realizados.
            </Text>
          </Alert>
          <Button
            color="orange"
            variant="light"
            size="sm"
            leftSection={<IconFileInvoice size={14} />}
            onClick={onRegistrar}
            fullWidth
          >
            Registrar comprobantes
          </Button>
        </Stack>
      ) : (
        <Stack gap="xs">
          {facturas.map((f, i) => (
            <Stack key={i} gap={2}>
              <Group gap="xs" justify="space-between">
                <Group gap="xs" style={{ flex: 1 }}>
                  <IconCircleCheck
                    size={14}
                    color="var(--mantine-color-emerald-6)"
                  />
                  <Text size="xs" fw={500}>
                    {f.nombre_proveedor}
                  </Text>
                </Group>
                <Text size="xs" fw={600} c="orange">
                  ${Number(f.monto).toFixed(2)}
                </Text>
              </Group>
              {f.categoria_factura_id > 0 && (
                <Group gap={4} ml={22}>
                  <Badge size="xs" color="orange" variant="dot">
                    {categoriaOptions.find(
                      c => Number(c.value) === f.categoria_factura_id
                    )?.label ?? `Categoría ${f.categoria_factura_id}`}
                  </Badge>
                  <Text size="xs" c="dimmed">
                    {f.tipo_comprobante
                      ? f.tipo_comprobante.charAt(0).toUpperCase() +
                        f.tipo_comprobante.slice(1)
                      : ''}
                  </Text>
                </Group>
              )}
            </Stack>
          ))}
          <Button
            size="xs"
            variant="subtle"
            color="orange"
            leftSection={<IconPencil size={12} />}
            onClick={onEditar}
          >
            Editar comprobantes
          </Button>
        </Stack>
      )}
    </Card>
  )
}
