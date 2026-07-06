'use client'

import {
  Table, NumberInput, TextInput,
  ActionIcon, Text, Stack,
} from '@mantine/core'
import { IconTrash } from '@tabler/icons-react'
import { Controller, type Control } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { RecetaFormData } from '../schemas/receta.schema'

interface Props {
  index:      number
  control:    Control<RecetaFormData>
  nombre:     string
  stock:      number
  onEliminar: () => void
}

export function ItemRecetaRow({
  index, control, nombre, stock, onEliminar,
}: Props) {
  const contained = useContainedInput()

  return (
    <Table.Tr>
      <Table.Td>
        <Stack gap={0}>
          <Text size="sm" fw={500}>{nombre}</Text>
          <Text size="xs" c="dimmed">Stock: {stock}</Text>
        </Stack>
      </Table.Td>
      <Table.Td>
        <Controller
          name={`items.${index}.cantidad_prescrita`}
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
          name={`items.${index}.dosis`}
          control={control}
          render={({ field }) => (
            <TextInput
              size="xs"
              placeholder="Ej: 1 tableta"
              {...contained}
              value={field.value}
              onChange={(e) => field.onChange(e.currentTarget.value)}
            />
          )}
        />
      </Table.Td>
      <Table.Td>
        <Controller
          name={`items.${index}.frecuencia`}
          control={control}
          render={({ field }) => (
            <TextInput
              size="xs"
              placeholder="Ej: Cada 8 horas"
              {...contained}
              value={field.value}
              onChange={(e) => field.onChange(e.currentTarget.value)}
            />
          )}
        />
      </Table.Td>
      <Table.Td>
        <Controller
          name={`items.${index}.duracion`}
          control={control}
          render={({ field }) => (
            <TextInput
              size="xs"
              placeholder="Ej: 5 días"
              {...contained}
              value={field.value}
              onChange={(e) => field.onChange(e.currentTarget.value)}
            />
          )}
        />
      </Table.Td>
      <Table.Td>
        <Controller
          name={`items.${index}.observaciones`}
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
