import { Text } from '@mantine/core'
import { IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { TableActions } from '@/components/ui'

type Renglon = { id: string; consumible_id: number }

/**
 * Los renglones de un ingreso o de una solicitud a bodega: el consumible, la
 * cantidad —que cada formulario pinta con su `Controller`— y quitarlo.
 */
export const columnasRenglones = <R extends Renglon>({ nombre, cantidad, onQuitar }: {
  nombre: (consumibleId: number) => string
  cantidad: (indice: number) => React.ReactNode
  onQuitar: (indice: number) => void
}): DataTableColumn<R>[] => [
  { accessor: 'consumible_id', title: 'Consumible', render: (r) => <Text size="sm">{nombre(r.consumible_id)}</Text> },
  { accessor: 'cantidad', title: 'Cantidad', width: 130, render: (_r, i) => cantidad(i) },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (_r, i) => <TableActions actions={[{ label: 'Quitar', icon: <IconTrash size={14} />, color: 'red', onClick: () => onQuitar(i) }]} />,
  },
]
