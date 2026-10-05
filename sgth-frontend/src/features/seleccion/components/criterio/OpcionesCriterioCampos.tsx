'use client'

import { ActionIcon, Button, Card, Group, NumberInput, Stack, Text, TextInput, Tooltip } from '@mantine/core'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { Controller, useFieldArray, type Control, type FieldErrors, type UseFormRegister } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { CriterioFormData } from '../../schemas/criterio.schema'

interface Props {
  control:  Control<CriterioFormData>
  register: UseFormRegister<CriterioFormData>
  errors:   FieldErrors<CriterioFormData>
}

/** Las opciones de un criterio de opción única o múltiple, con `useFieldArray`. */
export function OpcionesCriterioCampos({ control, register, errors }: Props) {
  const contained = useContainedInput('sm')
  const { fields, append, remove } = useFieldArray({ control, name: 'opciones' })

  return (
    <Stack gap="xs">
      <Group justify="space-between">
        <Text size="sm" fw={500}>Opciones de calificación</Text>
        <Button size="compact-xs" variant="subtle" leftSection={<IconPlus size={12} />}
          onClick={() => append({ etiqueta: '', puntaje: 0 })}>
          Agregar opción
        </Button>
      </Group>

      {errors.opciones?.message && <Text size="xs" c="red">{errors.opciones.message}</Text>}

      {fields.map((f, i) => (
        <Card key={f.id} withBorder radius="md" p="sm">
          <Group gap="sm" wrap="nowrap" align="flex-start">
            <TextInput
              label="Opción"
              placeholder="Ej: Título de cuarto nivel"
              style={{ flex: 1 }}
              {...contained}
              {...register(`opciones.${i}.etiqueta`)}
              error={errors.opciones?.[i]?.etiqueta?.message}
            />
            <Controller
              name={`opciones.${i}.puntaje`}
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Puntos"
                  style={{ width: 100 }}
                  min={0}
                  decimalScale={2}
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(v === '' ? 0 : Number(v))}
                  error={errors.opciones?.[i]?.puntaje?.message}
                />
              )}
            />
            <Tooltip label="Quitar opción">
              <ActionIcon size="sm" color="red" variant="subtle" mt={22} aria-label={`Quitar la opción ${i + 1}`}
                onClick={() => remove(i)} disabled={fields.length === 1}>
                <IconTrash size={13} />
              </ActionIcon>
            </Tooltip>
          </Group>
        </Card>
      ))}
    </Stack>
  )
}
