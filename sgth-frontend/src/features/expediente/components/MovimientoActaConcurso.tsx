'use client'

import { Grid, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import type { MovimientoFormData } from '../schemas/movimiento.schema'

/**
 * El acta final del concurso, número y fecha: la del ingreso de prueba (fase
 * 3.2) y la del ascenso (fase 3.3), que se ganan en concurso. Se exige al
 * suscribir, así que el borrador puede dejarse sin ella.
 */
export function MovimientoActaConcurso({ form }: { form: UseFormReturn<MovimientoFormData> }) {
  const contained = useContainedInput()
  const { control, register, formState: { errors } } = form

  return (
    <Grid gap="sm">
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Acta final del concurso N.º"
          placeholder="Ej. ACTA-CONC-2026-004"
          withAsterisk
          error={errors.acta_concurso_numero?.message}
          {...contained}
          {...register('acta_concurso_numero')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="acta_concurso_fecha"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha del acta"
              placeholder="Seleccionar fecha"
              valueFormat="DD/MM/YYYY"
              withAsterisk
              maxDate={new Date()}
              popoverProps={{ withinPortal: true, zIndex: 1100 }}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d))}
              error={errors.acta_concurso_fecha?.message}
              {...contained}
            />
          )}
        />
      </Grid.Col>
    </Grid>
  )
}
