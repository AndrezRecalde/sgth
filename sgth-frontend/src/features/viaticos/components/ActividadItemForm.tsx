'use client'

import { Grid, Paper, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput, TimeInput } from '@mantine/dates'
import {
  Controller,
  type Control,
  type FieldErrors,
  type UseFormRegister,
} from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import type { ActividadesFormData } from '../schemas/liquidacion.schema'
import { ItemCabecera } from './ItemCabecera'

interface Props {
  index:       number
  control:     Control<ActividadesFormData>
  register:    UseFormRegister<ActividadesFormData>
  errors:      FieldErrors<ActividadesFormData>
  minFecha?:   Date
  maxFecha?:   Date
  /** Sin esto no se puede quitar: queda al menos una. */
  onEliminar?: () => void
}

/** Una actividad: el día, el horario, el lugar y qué se hizo. */
export function ActividadItemForm({
  index, control, register, errors, minFecha, maxFecha, onEliminar,
}: Props) {
  const contained = useContainedInput()
  const err = errors.actividades?.[index]

  return (
    <Paper withBorder radius="md" p="md">
      <ItemCabecera
        titulo={`Actividad ${index + 1}`}
        onEliminar={onEliminar}
        etiquetaEliminar="Quitar esta actividad"
      />

      <Grid>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Controller
            name={`actividades.${index}.fecha`}
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha"
                valueFormat="DD/MM/YYYY"
                minDate={minFecha}
                maxDate={maxFecha}
                {...contained}
                value={toDateValue(field.value)}
                onChange={(v) => field.onChange(fromDateValue(v))}
                error={err?.fecha?.message}
              />
            )}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 6, sm: 4 }}>
          <TimeInput
            label="Desde"
            {...contained}
            {...register(`actividades.${index}.hora_inicio`)}
            error={err?.hora_inicio?.message}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 6, sm: 4 }}>
          <TimeInput
            label="Hasta"
            {...contained}
            {...register(`actividades.${index}.hora_fin`)}
            error={err?.hora_fin?.message}
          />
        </Grid.Col>
        {/* A lo ancho: a media fila dejaba un hueco a su lado. */}
        <Grid.Col span={12}>
          <TextInput
            label="Lugar"
            placeholder="Ej: Ministerio de Trabajo, Quito"
            {...contained}
            {...register(`actividades.${index}.lugar`)}
            error={err?.lugar?.message}
          />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea
            label="Qué se hizo"
            placeholder="Las actividades de ese día"
            autosize
            minRows={2}
            maxRows={4}
            {...contained}
            {...register(`actividades.${index}.descripcion`)}
            error={err?.descripcion?.message}
          />
        </Grid.Col>
      </Grid>
    </Paper>
  )
}
