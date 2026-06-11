'use client'

import {
  Card, Grid, Textarea, ActionIcon,
  Group, Text, Badge, TimeInput, TextInput,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconTrash } from '@tabler/icons-react'
import {
  Controller,
  type Control,
  type UseFormRegister,
  type FieldErrors,
} from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'

type ActividadFormData = {
  actividades: {
    fecha:       string
    hora_inicio: string
    hora_fin:    string
    descripcion: string
    lugar:       string
  }[]
}

interface Props {
  index:         number
  control:       Control<ActividadFormData>
  register:      UseFormRegister<ActividadFormData>
  errors:        FieldErrors<ActividadFormData>
  minFecha?:     Date
  maxFecha?:     Date
  onEliminar:    () => void
  puedeEliminar: boolean
}

const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const [y, m, d] = v.slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d)
}

const safeFormatDate = (v: Date | string | null | undefined): string => {
  if (!v) return ''
  const d = new Date(v)
  if (isNaN(d.getTime())) return ''
  if (d.getUTCHours() === 0 && d.getUTCMinutes() === 0) {
    return d.toISOString().slice(0, 10)
  }
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

export function ActividadItemForm({
  index, control, register, errors,
  minFecha, maxFecha, onEliminar, puedeEliminar,
}: Props) {
  const contained    = useContainedInput()
  const errActividad = errors.actividades?.[index]

  return (
    <Card withBorder radius="md" p="sm">
      <Group justify="space-between" mb="xs">
        <Group gap="xs">
          <Badge size="sm" color="blue" variant="light" circle>
            {index + 1}
          </Badge>
          <Text size="sm" fw={600}>
            Actividad {index + 1}
          </Text>
        </Group>
        {puedeEliminar && (
          <ActionIcon
            size="sm" color="red" variant="subtle"
            onClick={onEliminar}
          >
            <IconTrash size={13} />
          </ActionIcon>
        )}
      </Group>

      <Grid>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Controller
            name={`actividades.${index}.fecha`}
            control={control}
            render={({ field: f }) => (
              <DatePickerInput
                label="Fecha de la actividad"
                placeholder="Seleccionar"
                valueFormat="DD/MM/YYYY"
                minDate={minFecha}
                maxDate={maxFecha}
                popoverProps={{ withinPortal: true }}
                {...contained}
                value={toDate(f.value)}
                onChange={(v) => f.onChange(safeFormatDate(v))}
                error={errActividad?.fecha?.message}
              />
            )}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 6, sm: 4 }}>
          <TimeInput
            label="Hora inicio"
            {...contained}
            {...register(`actividades.${index}.hora_inicio`)}
            error={errActividad?.hora_inicio?.message}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 6, sm: 4 }}>
          <TimeInput
            label="Hora fin"
            {...contained}
            {...register(`actividades.${index}.hora_fin`)}
            error={errActividad?.hora_fin?.message}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Lugar"
            placeholder="Ej: Ministerio de Trabajo — Quito"
            {...contained}
            {...register(`actividades.${index}.lugar`)}
            error={errActividad?.lugar?.message}
          />
        </Grid.Col>
      </Grid>

      <Textarea
        label="Descripción de la actividad"
        placeholder="Describa las actividades realizadas en este día"
        autosize
        minRows={2}
        maxRows={4}
        mt="xs"
        {...contained}
        {...register(`actividades.${index}.descripcion`)}
        error={errActividad?.descripcion?.message}
      />
    </Card>
  )
}
