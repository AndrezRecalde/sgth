'use client'

import {
  Card, Grid, NumberInput, TextInput,
  ActionIcon, Text, Group, Stack,
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
  concentracion?: string | null
  presentacion?:  string | null
  onEliminar: () => void
}

export function ItemRecetaRow({
  index, control, nombre, stock,
  concentracion, presentacion, onEliminar,
}: Props) {
  const contained = useContainedInput()

  return (
    <Card
      withBorder
      radius="md"
      p="sm"
      style={{
        borderLeft: '3px solid var(--mantine-color-blue-6)',
      }}
    >
      <Stack gap="sm">
        <Group justify="space-between" align="flex-start">
          <Stack gap={0}>
            <Text size="sm" fw={500}>{nombre}</Text>
            <Text size="xs" c="dimmed">
              {[concentracion, presentacion].filter(Boolean).join(' · ')}
              {stock !== undefined && ` · Stock: ${stock} unid.`}
            </Text>
          </Stack>
          <ActionIcon
            size="sm"
            color="red"
            variant="subtle"
            onClick={onEliminar}
            aria-label="Quitar medicamento"
          >
            <IconTrash size={14} />
          </ActionIcon>
        </Group>

        <Grid>
          <Grid.Col span={3}>
            <Controller
              name={`items.${index}.cantidad_prescrita`}
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Cantidad"
                  size="xs"
                  min={1}
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(Number(v) || 1)}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={4}>
            <Controller
              name={`items.${index}.dosis`}
              control={control}
              render={({ field }) => (
                <TextInput
                  label="Dosis"
                  size="xs"
                  placeholder="Ej: 1 tableta"
                  {...contained}
                  value={field.value}
                  onChange={(e) => field.onChange(e.currentTarget.value)}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={5}>
            <Controller
              name={`items.${index}.frecuencia`}
              control={control}
              render={({ field }) => (
                <TextInput
                  label="Frecuencia"
                  size="xs"
                  placeholder="Ej: Cada 8 horas"
                  {...contained}
                  value={field.value}
                  onChange={(e) => field.onChange(e.currentTarget.value)}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={4}>
            <Controller
              name={`items.${index}.duracion`}
              control={control}
              render={({ field }) => (
                <TextInput
                  label="Duración"
                  size="xs"
                  placeholder="Ej: 7 días"
                  {...contained}
                  value={field.value}
                  onChange={(e) => field.onChange(e.currentTarget.value)}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={8}>
            <Controller
              name={`items.${index}.observaciones`}
              control={control}
              render={({ field }) => (
                <TextInput
                  label="Observaciones"
                  size="xs"
                  placeholder="Ej: Tomar con alimentos (opcional)"
                  {...contained}
                  value={field.value ?? ''}
                  onChange={(e) => field.onChange(e.currentTarget.value)}
                />
              )}
            />
          </Grid.Col>
        </Grid>
      </Stack>
    </Card>
  )
}
