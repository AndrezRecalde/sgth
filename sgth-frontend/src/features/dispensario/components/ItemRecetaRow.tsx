'use client'

import {
  Card, Grid, NumberInput, TextInput,
  ActionIcon, Text, Group, Stack,
} from '@mantine/core'
import { IconTrash } from '@tabler/icons-react'
import { Controller, type Control } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { StatusBadge } from '@/components/ui'
import type { RecetaFormData } from '../schemas/receta.schema'

interface Props {
  index:      number
  control:    Control<RecetaFormData>
  nombre:     string
  /** La farmacia no lo maneja: no hay ficha de inventario ni stock que mirar. */
  externo:    boolean
  stock:      number
  concentracion?: string | null
  presentacion?:  string | null
  onEliminar: () => void
}

export function ItemRecetaRow({
  index, control, nombre, externo, stock,
  concentracion, presentacion, onEliminar,
}: Props) {
  const contained = useContainedInput()

  return (
    <Card
      withBorder
      radius="md"
      p="sm"
      style={{
        borderLeft: `3px solid var(--mantine-color-${
          externo ? 'amber' : 'blue'
        }-6)`,
      }}
    >
      <Stack gap="sm">
        <Group justify="space-between" align="flex-start">
          <Stack gap={4}>
            <Group gap="xs" wrap="nowrap">
              <Text size="sm" fw={500}>{nombre}</Text>
              {externo && (
                <StatusBadge tone="warning" size="xs">
                  Fuera de farmacia
                </StatusBadge>
              )}
            </Group>
            <Text size="xs" c="dimmed">
              {externo
                ? 'El dispensario no lo maneja: el paciente lo adquiere fuera.'
                : [
                    [concentracion, presentacion].filter(Boolean).join(' · '),
                    stock === 0
                      ? 'Sin existencias hoy'
                      : `Stock: ${stock} unid.`,
                  ].filter(Boolean).join(' · ')}
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
