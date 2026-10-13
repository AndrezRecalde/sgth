import { ActionIcon, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { Controller, type Control, type FieldErrors } from 'react-hook-form'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import type { ContratoFormData } from '../schemas/contrato.schema'

type Fila = { id: string }

interface Opciones {
  control: Control<ContratoFormData>
  errors: FieldErrors<ContratoFormData>
  contained: object
  quitar: (indice: number) => void
}

/** Las ventanas de mantenimiento del contrato, capturadas fila por fila. */
export const columnasCronograma = ({ control, errors, contained, quitar }: Opciones): DataTableColumn<Fila>[] => {
  const fecha = (campo: 'fecha_desde' | 'fecha_hasta', label: string) =>
    function CeldaFecha(_: Fila, i: number) {
      return (
        <Controller name={`cronograma.${i}.${campo}`} control={control} render={({ field }) => (
          <DatePickerInput
            label={label}
            placeholder="Seleccionar"
            valueFormat="DD/MM/YYYY"
            error={errors.cronograma?.[i]?.[campo]?.message}
            {...contained}
            value={toDateValue(field.value)}
            onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')}
          />
        )} />
      )
    }

  return [
    { accessor: 'desde', title: 'Desde', width: 170, render: fecha('fecha_desde', 'Desde') },
    { accessor: 'hasta', title: 'Hasta', width: 170, render: fecha('fecha_hasta', 'Hasta') },
    {
      accessor: 'descripcion',
      title: 'Descripción',
      render: (_, i) => (
        <Controller name={`cronograma.${i}.descripcion`} control={control} render={({ field }) => (
          <TextInput label="Descripción" placeholder="Ej.: Segundo año" {...contained} {...field} />
        )} />
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (_, i) => (
        <ActionIcon variant="subtle" color="red" aria-label="Quitar la ventana" onClick={() => quitar(i)}>
          <IconTrash size={16} />
        </ActionIcon>
      ),
    },
  ]
}
