'use client'

import { Divider, Grid, NumberInput, Select, Stack, Text, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDate, toDate } from '../utils/fechas'
import type { VacacionFormData } from './vacacion.schema'
import classes from './VacacionFechasCampos.module.css'

const FECHAS = [
  { name: 'fecha_inicio',  label: 'Fecha inicio' },
  { name: 'fecha_fin',     label: 'Fecha fin' },
  { name: 'fecha_retorno', label: 'Fecha de retorno' },
] as const

interface Props {
  form: UseFormReturn<VacacionFormData>
}

/**
 * Cuándo: las tres fechas, los días que salen de ellas y la observación.
 *
 * Los días y su tipo no se escriben: los calcula `useVacacionForm` a partir de
 * las fechas, en días calendario.
 */
export function VacacionFechasCampos({ form }: Props) {
  const contained = useContainedInput()
  const { control, register, formState: { errors } } = form

  return (
    <>
      <Divider label="Fechas" labelPosition="left" />

      <Grid>
        {FECHAS.map(({ name, label }) => (
          <Grid.Col key={name} span={{ base: 12, sm: 4 }}>
            <Controller
              name={name}
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label={label}
                  placeholder="Seleccionar"
                  valueFormat="YYYY-MM-DD"
                  {...contained}
                  value={toDate(field.value)}
                  onChange={(d) => field.onChange(fromDate(d ?? null) ?? '')}
                  error={errors[name]?.message}
                />
              )}
            />
          </Grid.Col>
        ))}

        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Controller
            name="dias_solicitados"
            control={control}
            render={({ field }) => (
              <Stack gap={4}>
                <NumberInput
                  label="Días solicitados"
                  min={0}
                  max={365}
                  readOnly
                  className={`${classes.calculado} ${classes.dias}`}
                  {...contained}
                  value={field.value}
                  error={errors.dias_solicitados?.message}
                />
                <Text size="xs" c="dimmed">
                  Calculado automáticamente según las fechas (días calendario)
                </Text>
              </Stack>
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Controller
            name="tipo_dias"
            control={control}
            render={({ field }) => (
              <Stack gap={4}>
                <Select
                  label="Tipo de días"
                  data={[{ value: 'calendario', label: 'Calendario' }]}
                  readOnly
                  className={classes.calculado}
                  {...contained}
                  value={field.value}
                />
                <Text size="xs" c="dimmed">
                  La LOSEP y el Código del Trabajo cuentan días calendario
                </Text>
              </Stack>
            )}
          />
        </Grid.Col>
      </Grid>

      <Textarea
        label="Observación"
        placeholder="Observaciones adicionales (opcional)"
        rows={2}
        {...contained}
        {...register('observacion')}
      />
    </>
  )
}
