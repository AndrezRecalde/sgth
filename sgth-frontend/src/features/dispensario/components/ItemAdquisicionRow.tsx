'use client'

import {
  Table, NumberInput, TextInput, ActionIcon,
  Text,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { IconTrash } from '@tabler/icons-react'
import { Controller, type Control } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { AdquisicionFormData } from '../schemas/adquisicion.schema'

interface Props {
  index:      number
  control:    Control<AdquisicionFormData>
  nombre:     string
  onEliminar: () => void
}

function toDate(v?: string | null): Date | null {
  if (!v) return null
  const [y, m, d] = v.slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d)
}

function fromDate(d: Date | string | null): string | null {
  if (!d) return null
  const date = typeof d === 'string' ? toDate(d) : d
  if (!date || isNaN(date.getTime())) return null
  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-')
}

export function ItemAdquisicionRow({
  index, control, nombre, onEliminar,
}: Props) {
  const contained = useContainedInput()

  return (
    <Table.Tr>
      <Table.Td>
        <Text size="sm" fw={500}>{nombre}</Text>
      </Table.Td>
      <Table.Td>
        <Controller
          name={`items.${index}.cantidad`}
          control={control}
          render={({ field }) => (
            <NumberInput
              size="xs"
              min={1}
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(Number(v) || 1)}
            />
          )}
        />
      </Table.Td>
      <Table.Td>
        <Controller
          name={`items.${index}.lote`}
          control={control}
          render={({ field }) => (
            <TextInput
              size="xs"
              placeholder="Opcional"
              {...contained}
              value={field.value ?? ''}
              onChange={(e) => field.onChange(e.currentTarget.value)}
            />
          )}
        />
      </Table.Td>
      <Table.Td>
        <Controller
          name={`items.${index}.fecha_caducidad`}
          control={control}
          render={({ field }) => (
            <DatePickerInput
              size="xs"
              placeholder="Opcional"
              valueFormat="DD/MM/YYYY"
              clearable
              {...contained}
              value={toDate(field.value)}
              onChange={(d) => field.onChange(fromDate(d))}
            />
          )}
        />
      </Table.Td>
      <Table.Td>
        <Controller
          name={`items.${index}.precio_unitario`}
          control={control}
          render={({ field }) => (
            <NumberInput
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
      </Table.Td>
      <Table.Td>
        <ActionIcon
          size="sm" color="red" variant="subtle"
          onClick={onEliminar}
        >
          <IconTrash size={14} />
        </ActionIcon>
      </Table.Td>
    </Table.Tr>
  )
}
