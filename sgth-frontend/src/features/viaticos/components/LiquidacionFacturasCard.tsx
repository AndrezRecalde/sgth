'use client'

import { Button, Group, Paper, Stack, Text } from '@mantine/core'
import { IconFileInvoice, IconPencil } from '@tabler/icons-react'
import { CountBadge, StatusBadge } from '@/components/ui'
import { REVISION_LABELS, TONO_REVISION } from '../utils/revisionComprobantes'
import { dolares } from '../utils/monto'
import type { FacturaData } from '../schemas/liquidacion.schema'
import type { CategoriaFactura } from '@/types/api'

interface Props {
  facturas: FacturaData[]
  categorias: CategoriaFactura[]
  /** Sin estas dos, el bloque es de solo lectura. */
  onRegistrar?: () => void
  onEditar?: () => void
}

const TIPO: Record<string, string> = {
  factura: 'Factura', ticket: 'Ticket', recibo: 'Recibo', otro: 'Otro',
}

/*
| Los comprobantes que presenta el servidor.
|
| El monto va en el color del texto: antes salía en ámbar, que en el sistema
| significa advertencia. Y cada comprobante llevaba una palomita verde aunque
| Financiero no lo hubiera revisado; ahora lo único que se marca es la
| revisión, cuando ya la hay.
*/
export function LiquidacionFacturasCard({ facturas, categorias, onRegistrar, onEditar }: Props) {
  const categoria = (id: number) =>
    categorias.find((c) => Number(c.id) === id)?.nombre ?? `Categoría ${id}`

  return (
    <Paper withBorder radius="md" p="md" h="100%">
      <Group justify="space-between" mb="sm">
        <Text fw={600} size="sm">Comprobantes</Text>
        {facturas.length > 0 && <CountBadge>{facturas.length}</CountBadge>}
      </Group>

      {facturas.length === 0 ? (
        <Stack gap="sm">
          <Text size="sm" c="dimmed">
            Registre las facturas y tickets de los gastos del viaje.
          </Text>
          {onRegistrar && (
            <Button variant="light" leftSection={<IconFileInvoice size={16} />} onClick={onRegistrar}>
              Registrar comprobantes
            </Button>
          )}
        </Stack>
      ) : (
        <Stack gap="sm">
          {facturas.map((f, i) => (
            <Stack key={i} gap={2}>
              <Group justify="space-between" gap="xs" wrap="nowrap">
                <Text size="sm" fw={500} truncate>{f.nombre_proveedor}</Text>
                <Group gap={6} wrap="nowrap">
                  {/* Tras una devolución, qué aceptó Financiero y qué observó. */}
                  {f.estado_revision && f.estado_revision !== 'pendiente' && (
                    <StatusBadge tone={TONO_REVISION[f.estado_revision]} size="xs">
                      {REVISION_LABELS[f.estado_revision]}
                    </StatusBadge>
                  )}
                  <Text size="sm" fw={600}>{dolares(f.monto)}</Text>
                </Group>
              </Group>
              <Text size="xs" c="dimmed">
                {[f.categoria_factura_id > 0 ? categoria(f.categoria_factura_id) : null, TIPO[f.tipo_comprobante]]
                  .filter(Boolean)
                  .join(' · ')}
              </Text>
              {f.estado_revision === 'observada' && f.observacion_revision && (
                <Text size="xs" c="red">Observación: {f.observacion_revision}</Text>
              )}
            </Stack>
          ))}
          {onEditar && (
            <Button variant="subtle" size="xs" leftSection={<IconPencil size={14} />} onClick={onEditar}>
              Editar comprobantes
            </Button>
          )}
        </Stack>
      )}
    </Paper>
  )
}
