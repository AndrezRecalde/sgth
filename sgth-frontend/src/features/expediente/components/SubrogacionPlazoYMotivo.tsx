'use client'

import { Group, Select, Stack, TextInput, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import { motivosPara } from '../utils/subrogaciones'
import type { SubrogacionFormData } from '../schemas/subrogacion.schema'
import type { TipoSubrogacion } from '@/types/api'

/** El plazo del acto y su justificación. */
export function SubrogacionPlazoYMotivo({
  form,
  tipo,
}: {
  form: UseFormReturn<SubrogacionFormData>
  tipo: TipoSubrogacion
}) {
  const contained = useContainedInput()
  const { control, register, formState: { errors } } = form

  return (
    <Stack gap="sm">
      <Group grow>
        <Controller
          name="fecha_inicio"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de inicio"
              placeholder="Seleccionar fecha"
              valueFormat="DD/MM/YYYY"
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValue(d))}
              error={errors.fecha_inicio?.message}
            />
          )}
        />
        <Controller
          name="fecha_fin"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de fin"
              placeholder="Seleccionar fecha"
              valueFormat="DD/MM/YYYY"
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValue(d))}
              error={errors.fecha_fin?.message}
            />
          )}
        />
      </Group>

      <Controller
        name="motivo"
        control={control}
        render={({ field }) => (
          <Select
            label="Motivo"
            data={motivosPara(tipo)}
            {...contained}
            value={field.value}
            onChange={(v) => field.onChange(v ?? 'otro')}
            error={errors.motivo?.message}
          />
        )}
      />

      <TextInput
        label="Número de resolución"
        placeholder="Opcional"
        {...contained}
        {...register('resolucion_numero')}
        error={errors.resolucion_numero?.message}
      />

      <Textarea
        label="Observación"
        placeholder="Opcional"
        autosize
        minRows={2}
        {...contained}
        {...register('observacion')}
        error={errors.observacion?.message}
      />
    </Stack>
  )
}
