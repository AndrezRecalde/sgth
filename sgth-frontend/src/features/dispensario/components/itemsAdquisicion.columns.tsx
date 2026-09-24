'use client'

import { NumberInput, Stack, Text, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconTrash } from '@tabler/icons-react'
import { Controller, type Control, type FieldArrayWithId } from 'react-hook-form'
import type { DataTableColumn } from 'mantine-datatable'
import { TableActions } from '@/components/ui'
import { formatFechaMes, fromDateValueOrNull } from '@/lib/fecha'
import type { useContainedInput } from '@/hooks/useContainedInput'
import type { AdquisicionFormData } from '../schemas/adquisicion.schema'
import type { ItemAdquisicion } from '../services/adquisicionService'

function toDate(v?: string | null): Date | null {
  if (!v) return null
  const [y, m, d] = v.slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d)
}

/** Los medicamentos de una adquisición ya registrada, en su detalle. */
export const columnasItemsAdquisicion: DataTableColumn<ItemAdquisicion>[] = [
  {
    accessor: 'medicina.nombre',
    title: 'Medicina',
    render: (item) => (
      <Stack gap={0}>
        <Text size="sm" fw={500}>{item.medicina?.nombre ?? '—'}</Text>
        <Text size="xs" c="dimmed">{item.medicina?.concentracion ?? ''}</Text>
      </Stack>
    ),
  },
  { accessor: 'cantidad', title: 'Cantidad', width: 90, textAlign: 'center' },
  {
    accessor: 'lote',
    title: 'Lote',
    width: 110,
    render: (item) => <Text size="xs" ff="monospace">{item.lote ?? '—'}</Text>,
  },
  {
    accessor: 'fecha_caducidad',
    title: 'Caduca',
    width: 120,
    render: (item) => <Text size="xs">{formatFechaMes(item.fecha_caducidad)}</Text>,
  },
  {
    accessor: 'precio_unitario',
    title: 'P. unit.',
    width: 100,
    textAlign: 'right',
    render: (item) => (
      <Text size="sm" ff="monospace">
        {item.precio_unitario ? `$${Number(item.precio_unitario).toFixed(2)}` : '—'}
      </Text>
    ),
  },
]

type FilaFormulario = FieldArrayWithId<AdquisicionFormData, 'items', 'id'>

interface OpcionesFormulario {
  control: Control<AdquisicionFormData>
  contained: ReturnType<typeof useContainedInput>
  onQuitar: (index: number) => void
}

/**
 * Los medicamentos mientras se captura la adquisición: cada celda es un campo
 * del arreglo `items` del formulario. El índice sale de la posición de la fila,
 * que coincide con la del arreglo porque la tabla no ordena ni pagina.
 */
export function getColumnasCapturaItems(
  { control, contained, onQuitar }: OpcionesFormulario,
): DataTableColumn<FilaFormulario>[] {
  return [
    {
      accessor: 'nombre_medicina',
      title: 'Medicina',
      render: (fila) => <Text size="sm" fw={500}>{fila.nombre_medicina}</Text>,
    },
    {
      accessor: 'cantidad',
      title: 'Cantidad',
      width: 100,
      render: (_fila, index) => (
        <Controller
          name={`items.${index}.cantidad`}
          control={control}
          render={({ field }) => (
            <NumberInput
              aria-label="Cantidad"
              // Unidades ingresadas: enteras. El precio de al lado NO lleva
              // esta prop: ahí los centavos son legítimos.
              allowDecimal={false}
              size="xs"
              min={1}
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(Number(v) || 1)}
            />
          )}
        />
      ),
    },
    {
      accessor: 'lote',
      title: 'Lote',
      width: 130,
      render: (_fila, index) => (
        <Controller
          name={`items.${index}.lote`}
          control={control}
          render={({ field }) => (
            <TextInput
              aria-label="Lote"
              size="xs"
              placeholder="Opcional"
              {...contained}
              value={field.value ?? ''}
              onChange={(e) => field.onChange(e.currentTarget.value)}
            />
          )}
        />
      ),
    },
    {
      accessor: 'fecha_caducidad',
      title: 'Caducidad',
      width: 150,
      render: (_fila, index) => (
        <Controller
          name={`items.${index}.fecha_caducidad`}
          control={control}
          render={({ field }) => (
            <DatePickerInput
              aria-label="Caducidad"
              size="xs"
              placeholder="Opcional"
              valueFormat="DD/MM/YYYY"
              clearable
              {...contained}
              value={toDate(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d))}
            />
          )}
        />
      ),
    },
    {
      accessor: 'precio_unitario',
      title: 'Precio unit.',
      width: 120,
      render: (_fila, index) => (
        <Controller
          name={`items.${index}.precio_unitario`}
          control={control}
          render={({ field }) => (
            <NumberInput
              aria-label="Precio unitario"
              size="xs"
              placeholder="Opcional"
              decimalScale={2}
              prefix="$"
              {...contained}
              value={field.value ?? undefined}
              onChange={(v) => field.onChange(v ? Number(v) : null)}
            />
          )}
        />
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (_fila, index) => (
        <TableActions
          actions={[
            {
              label: 'Quitar de la adquisición',
              icon: <IconTrash size={14} />,
              color: 'red',
              onClick: () => onQuitar(index),
            },
          ]}
        />
      ),
    },
  ]
}
