'use client'

import { Grid, NumberInput, Select, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import {
  Controller,
  type Control,
  type FieldErrors,
  type UseFormRegister,
} from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import {
  MULTA_MAX,
  SUSPENSION_MAX_DIAS,
  type ResolucionSumarioFormData,
} from '../schemas/resolucionSumario.schema'
import { TIPO_FALTA_LABELS, TIPO_SANCION_LABELS } from '../utils/etiquetas'
import type { TipoFalta, TipoSancion } from '@/types/api'

const FALTA_OPTIONS = (Object.keys(TIPO_FALTA_LABELS) as TipoFalta[])
  .map((f) => ({ value: f, label: TIPO_FALTA_LABELS[f] }))

const SANCION_OPTIONS = (Object.keys(TIPO_SANCION_LABELS) as TipoSancion[])
  .map((s) => ({ value: s, label: TIPO_SANCION_LABELS[s] }))

interface Props {
  control: Control<ResolucionSumarioFormData>
  register: UseFormRegister<ResolucionSumarioFormData>
  errors: FieldErrors<ResolucionSumarioFormData>
  /** La sanción elegida: decide qué cifra se pide. */
  sancion: TipoSancion | undefined
}

/**
 * Los campos de la resolución. El porcentaje y los días se piden solo para la
 * sanción que los usa: una multa no tiene días y una suspensión no tiene
 * porcentaje, y pedir las dos cifras siempre invita a guardar la que no toca.
 */
export function ResolucionSancionCampos({ control, register, errors, sancion }: Props) {
  const contained = useContainedInput()

  return (
    <Grid>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="tipo_falta"
          control={control}
          render={({ field }) => (
            <Select
              label="Gravedad de la falta"
              placeholder="Seleccione"
              data={FALTA_OPTIONS}
              error={errors.tipo_falta?.message}
              {...contained}
              value={field.value ?? null}
              onChange={field.onChange}
            />
          )}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="tipo_sancion"
          control={control}
          render={({ field }) => (
            <Select
              label="Sanción que se impone"
              placeholder="Seleccione"
              data={SANCION_OPTIONS}
              error={errors.tipo_sancion?.message}
              {...contained}
              value={field.value ?? null}
              onChange={field.onChange}
            />
          )}
        />
      </Grid.Col>

      {sancion === 'multa' && (
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="porcentaje_multa"
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Multa (% de la remuneración)"
                description={`El Art. 43 de la LOSEP no admite más del ${MULTA_MAX}%`}
                placeholder="Ej: 5"
                decimalScale={2}
                min={0.01}
                max={MULTA_MAX}
                hideControls
                error={errors.porcentaje_multa?.message}
                {...contained}
                value={field.value ?? ''}
                onChange={(v) => field.onChange(typeof v === 'number' ? v : undefined)}
              />
            )}
          />
        </Grid.Col>
      )}

      {sancion === 'suspension' && (
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="dias_suspension"
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Días de suspensión"
                description={`El Art. 43 de la LOSEP no admite más de ${SUSPENSION_MAX_DIAS} días`}
                placeholder="Ej: 15"
                min={1}
                max={SUSPENSION_MAX_DIAS}
                allowDecimal={false}
                hideControls
                error={errors.dias_suspension?.message}
                {...contained}
                value={field.value ?? ''}
                onChange={(v) => field.onChange(typeof v === 'number' ? v : undefined)}
              />
            )}
          />
        </Grid.Col>
      )}

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="fecha_efectiva"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Surte efecto desde"
              valueFormat="DD/MM/YYYY"
              error={errors.fecha_efectiva?.message}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(v) => field.onChange(fromDateValue(v))}
            />
          )}
        />
      </Grid.Col>

      <Grid.Col span={12}>
        <Textarea
          label="Observaciones"
          placeholder="Lo que conste en la resolución y convenga dejar en el expediente"
          rows={3}
          error={errors.observaciones?.message}
          {...contained}
          {...register('observaciones')}
        />
      </Grid.Col>
    </Grid>
  )
}
