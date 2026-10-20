'use client'

import { Grid, Text, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import type { MovimientoFormData } from '../schemas/movimiento.schema'

/**
 * El inicio del período de una autoridad electa (diseño, 9.14; TH 2; fase 4.1):
 * la credencial del CNE, que es obligatoria, el acta de posesión, que no, y la
 * fecha en que termina el período, que ese día cierra solo el vínculo. «Rige a
 * partir de» es el día de la posesión.
 *
 * El Viceprefecto que designa el Consejo Provincial, de una terna del Prefecto
 * (COOTAD Art. 47 n; 2026-10-20), empieza con la resolución, sin acta de
 * posesión, y termina con el período del Prefecto: ese fin lo pone el sistema.
 */
export function MovimientoDatosDelPeriodo({ form }: { form: UseFormReturn<MovimientoFormData> }) {
  const contained = useContainedInput()
  const { control, register, formState: { errors } } = form
  const designacion = useWatch({ control, name: 'causal' }) === 'designacion_del_consejo'

  if (designacion) {
    return (
      <Grid gap="sm">
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Resolución del Consejo N.º"
            placeholder="Ej. RES-CP-2027-031"
            withAsterisk
            error={errors.acto_origen_numero?.message}
            {...contained}
            {...register('acto_origen_numero')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="acto_origen_fecha"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha de la resolución"
                placeholder="Seleccionar fecha"
                valueFormat="DD/MM/YYYY"
                withAsterisk
                maxDate={new Date()}
                popoverProps={{ withinPortal: true, zIndex: 1100 }}
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                error={errors.acto_origen_fecha?.message}
                {...contained}
              />
            )}
          />
        </Grid.Col>
        <Grid.Col span={12}>
          <Text size="xs" c="dimmed">
            Termina con el período del Prefecto en funciones, y no lleva acta de posesión (COOTAD Art. 47 n).
          </Text>
        </Grid.Col>
      </Grid>
    )
  }

  return (
    <Grid gap="sm">
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Credencial del CNE N.º"
          placeholder="Ej. CNE-2027-0147"
          withAsterisk
          error={errors.credencial_cne_numero?.message}
          {...contained}
          {...register('credencial_cne_numero')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="credencial_cne_fecha"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de la credencial"
              placeholder="Seleccionar fecha"
              valueFormat="DD/MM/YYYY"
              withAsterisk
              maxDate={new Date()}
              popoverProps={{ withinPortal: true, zIndex: 1100 }}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d))}
              error={errors.credencial_cne_fecha?.message}
              {...contained}
            />
          )}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Acta de posesión N.º"
          placeholder="Opcional"
          error={errors.acta_posesion_numero?.message}
          {...contained}
          {...register('acta_posesion_numero')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="fecha_fin_propuesta"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fin del período"
              description="Ese día el vínculo se cierra solo."
              placeholder="Seleccionar fecha"
              valueFormat="DD/MM/YYYY"
              withAsterisk
              popoverProps={{ withinPortal: true, zIndex: 1100 }}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d))}
              error={errors.fecha_fin_propuesta?.message}
              {...contained}
            />
          )}
        />
      </Grid.Col>
    </Grid>
  )
}
