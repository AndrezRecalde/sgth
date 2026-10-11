'use client'

import { Grid, NumberInput, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import type { MovimientoFormData } from '../schemas/movimiento.schema'

/**
 * La sentencia que ordena la restitución [TH N15; fase 2.6]: la causa, quién
 * la dictó, cuándo y cuándo quedó ejecutoriada, y lo que el servidor dejó de
 * percibir. El puesto no se pide: vuelve al que tenía.
 */
export function MovimientoDatosJudiciales({ form }: { form: UseFormReturn<MovimientoFormData> }) {
  const contained = useContainedInput()
  const { control, register, formState: { errors } } = form

  const fecha = (name: 'fecha_sentencia' | 'fecha_ejecutoria', label: string) => (
    <Controller
      name={name}
      control={control}
      render={({ field }) => (
        <DatePickerInput
          label={label}
          placeholder="Seleccionar fecha"
          valueFormat="DD/MM/YYYY"
          withAsterisk
          maxDate={new Date()}
          popoverProps={{ withinPortal: true, zIndex: 1100 }}
          value={toDateValue(field.value)}
          onChange={(d) => field.onChange(fromDateValueOrNull(d))}
          error={errors[name]?.message}
          {...contained}
        />
      )}
    />
  )

  return (
    <div>
      <SectionHeading title="Sentencia" mb="xs" />
      <Grid gap="sm">
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Número de la causa"
            placeholder="Ej. 08281-2025-00123"
            withAsterisk
            error={errors.causa_judicial_numero?.message}
            {...contained}
            {...register('causa_judicial_numero')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Unidad judicial o tribunal"
            placeholder="Ej. Tribunal Distrital de lo Contencioso Administrativo"
            withAsterisk
            error={errors.unidad_judicial?.message}
            {...contained}
            {...register('unidad_judicial')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          {fecha('fecha_sentencia', 'Fecha de la sentencia')}
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          {fecha('fecha_ejecutoria', 'Fecha de ejecutoría')}
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller
            name="remuneraciones_dejadas_de_percibir"
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Remuneraciones dejadas de percibir"
                description="Lo que la sentencia ordena pagar; 0 si no ordena nada. La nómina lo aplicará cuando llegue esa fase."
                placeholder="0.00"
                min={0}
                decimalScale={2}
                hideControls
                withAsterisk
                error={errors.remuneraciones_dejadas_de_percibir?.message}
                value={field.value ?? ''}
                onChange={(v) => {
                  const n = typeof v === 'number' ? v : parseFloat(String(v))
                  field.onChange(Number.isFinite(n) ? n : null)
                }}
                {...contained}
              />
            )}
          />
        </Grid.Col>
      </Grid>
    </div>
  )
}
