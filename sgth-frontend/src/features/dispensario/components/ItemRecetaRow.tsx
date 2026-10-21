'use client'

import {
  Card, Grid, NumberInput, TextInput,
  ActionIcon, Text, Group, Stack,
} from '@mantine/core'
import { IconTrash } from '@tabler/icons-react'
import { Controller, useFormState, type Control } from 'react-hook-form'
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

  // Lo que no tiene campo propio: el nombre del externo (viene del buscador)
  // y el «del catálogo o externo» del esquema. Se dice bajo el nombre.
  const { errors } = useFormState({ control, name: `items.${index}` })
  const errorDelItem = errors.items?.[index]?.medicamento_externo?.message
    ?? errors.items?.[index]?.inventario_medicina_id?.message

  return (
    <Card
      withBorder
      radius="md"
      p="sm"
      style={{
        // Fuera de farmacia es un aviso; dentro, el acento del subsistema.
        borderLeft: `3px solid ${externo ? 'var(--mantine-color-amber-6)' : 'var(--sgth-accent)'}`,
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
            {errorDelItem && (
              <Text size="xs" c="red">{errorDelItem}</Text>
            )}
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
              render={({ field, fieldState }) => (
                <NumberInput
                  label="Cantidad"
                  // Unidades recetadas: enteras, como las exige el servidor.
                  allowDecimal={false}
                  size="xs"
                  min={1}
                  {...contained}
                  ref={field.ref}
                  value={field.value ?? ''}
                  // Vacío se queda vacío y el esquema lo dice. Antes volvía a 1
                  // al borrar, y escribir «5» dejaba «15».
                  onChange={(v) => field.onChange(typeof v === 'number' ? v : undefined)}
                  onBlur={field.onBlur}
                  error={fieldState.error?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={4}>
            <Controller
              name={`items.${index}.dosis`}
              control={control}
              render={({ field, fieldState }) => (
                <TextInput
                  label="Dosis"
                  size="xs"
                  placeholder="Ej: 1 tableta"
                  {...contained}
                  value={field.value}
                  onChange={(e) => field.onChange(e.currentTarget.value)}
                  ref={field.ref}
                  onBlur={field.onBlur}
                  error={fieldState.error?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={5}>
            <Controller
              name={`items.${index}.frecuencia`}
              control={control}
              render={({ field, fieldState }) => (
                <TextInput
                  label="Frecuencia"
                  size="xs"
                  placeholder="Ej: Cada 8 horas"
                  {...contained}
                  value={field.value}
                  onChange={(e) => field.onChange(e.currentTarget.value)}
                  ref={field.ref}
                  onBlur={field.onBlur}
                  error={fieldState.error?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={4}>
            <Controller
              name={`items.${index}.duracion`}
              control={control}
              render={({ field, fieldState }) => (
                <TextInput
                  label="Duración"
                  size="xs"
                  placeholder="Ej: 7 días"
                  {...contained}
                  value={field.value}
                  onChange={(e) => field.onChange(e.currentTarget.value)}
                  ref={field.ref}
                  onBlur={field.onBlur}
                  error={fieldState.error?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={8}>
            <Controller
              name={`items.${index}.observaciones`}
              control={control}
              render={({ field, fieldState }) => (
                <TextInput
                  label="Observaciones"
                  size="xs"
                  placeholder="Ej: Tomar con alimentos (opcional)"
                  {...contained}
                  value={field.value ?? ''}
                  onChange={(e) => field.onChange(e.currentTarget.value)}
                  ref={field.ref}
                  onBlur={field.onBlur}
                  error={fieldState.error?.message}
                />
              )}
            />
          </Grid.Col>
        </Grid>
      </Stack>
    </Card>
  )
}
