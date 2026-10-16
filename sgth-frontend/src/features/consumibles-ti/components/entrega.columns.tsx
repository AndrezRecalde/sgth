import { NumberInput, Stack, Text } from '@mantine/core'
import { IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import type { Consumible } from '../services/consumiblesService'
import { SaldoConsumible, detalleConsumible } from './consumibles.columns'

/** Un consumible que se puede entregar y cuántos se marcan (0 = no va). */
export type RenglonEntrega = { consumible: Consumible; cantidad: number; agregado: boolean }

/**
 * Los consumibles de la entrega: los compatibles con el equipo y los repuestos
 * que se agregan. Lo que guarda TIC no deja marcar más de su saldo; lo que
 * despacha Bodega, sin tope. El saldo va bajo el nombre para que la cantidad
 * quepa en el ancho de un teléfono, donde se entrega leyendo la etiqueta.
 */
export const columnasEntrega = ({ onCantidad, onQuitar }: {
  onCantidad: (consumibleId: number, cantidad: number) => void
  onQuitar: (consumibleId: number) => void
}): DataTableColumn<RenglonEntrega & { id: number }>[] => [
  {
    accessor: 'consumible',
    title: 'Consumible',
    render: ({ consumible: c }) => (
      <Stack gap={4} align="flex-start">
        <Stack gap={0}>
          <Text size="sm" fw={500}>{c.descripcion}</Text>
          <Text size="xs" c="dimmed">{detalleConsumible(c)}</Text>
        </Stack>
        {c.guarda_tic ? <SaldoConsumible c={c} /> : <StatusBadge>Lo despacha Bodega</StatusBadge>}
      </Stack>
    ),
  },
  {
    accessor: 'cantidad',
    title: 'Cantidad',
    width: 100,
    render: ({ consumible: c, cantidad }) => {
      const tope = c.guarda_tic ? (c.saldo ?? 0) : 999
      return (
        <NumberInput aria-label={`Cantidad de ${c.descripcion}`} size="xs" min={0} max={tope} allowDecimal={false} clampBehavior="strict"
          disabled={tope === 0} value={cantidad} onChange={(v) => onCantidad(c.id, typeof v === 'number' ? v : 0)} />
      )
    },
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: ({ consumible: c, agregado }) => (
      <TableActions actions={[{ label: 'Quitar', icon: <IconTrash size={14} />, color: 'red', hidden: !agregado, onClick: () => onQuitar(c.id) }]} />
    ),
  },
]
