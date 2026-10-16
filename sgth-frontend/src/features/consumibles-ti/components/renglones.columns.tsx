import { NumberInput, Text } from '@mantine/core'
import { IconTrash } from '@tabler/icons-react'
import { Controller, type Control, type FieldErrors } from 'react-hook-form'
import type { DataTableColumn } from 'mantine-datatable'
import { TableActions } from '@/components/ui'
import type { IngresoFormData } from '../schemas/movimiento.schema'

type Renglon = IngresoFormData['items'][number] & { id: string }

/** Los renglones de un ingreso: el consumible, la cantidad (que se edita en la celda) y quitarlo. */
export const columnasRenglones = ({ control, errores, nombre, onQuitar }: {
  control: Control<IngresoFormData>
  errores?: FieldErrors<IngresoFormData>['items']
  nombre: (id: number) => string
  onQuitar: (indice: number) => void
}): DataTableColumn<Renglon>[] => [
  { accessor: 'consumible_id', title: 'Consumible', render: (r) => <Text size="sm">{nombre(r.consumible_id)}</Text> },
  {
    accessor: 'cantidad',
    title: 'Cantidad',
    width: 130,
    render: (_r, i) => (
      <Controller name={`items.${i}.cantidad`} control={control} render={({ field }) => (
        <NumberInput aria-label="Cantidad" size="xs" min={1} max={9999} allowDecimal={false} hideControls
          error={errores?.[i]?.cantidad?.message}
          value={field.value} onChange={(v) => field.onChange(typeof v === 'number' ? v : Number.NaN)} />
      )} />
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (_r, i) => <TableActions actions={[{ label: 'Quitar', icon: <IconTrash size={14} />, color: 'red', onClick: () => onQuitar(i) }]} />,
  },
]
