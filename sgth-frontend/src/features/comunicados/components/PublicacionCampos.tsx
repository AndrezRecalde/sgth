'use client'

import { Grid, Stack, Switch } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useWatch, type Control, type FieldErrors } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValue, hoyIso, toDateValue } from '@/lib/fecha'
import { MAXIMO_FIJADOS } from '../constants/comunicados'
import type { ComunicadoFormData } from '../schemas/comunicado.schema'
import { AtajosFechaFin } from './AtajosFechaFin'

interface Props {
  control: Control<ComunicadoFormData>
  errors: FieldErrors<ComunicadoFormData>
}

/**
 * Cuándo se ve y cómo (docs/diseno-noticias-comunicados.md, 3.2, 3.4 y 3.5).
 * La confirmación de lectura es solo del comunicado oficial.
 */
export function PublicacionCampos({ control, errors }: Props) {
  const contained = useContainedInput()
  const [tipo, desde] = useWatch({ control, name: ['tipo', 'publicado_desde'] })
  const hoy = new Date()

  return (
    <Stack gap="md">
      <Grid gap="md">
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="publicado_desde"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha de inicio"
                description="Vacía o de hoy, sale al publicar. Una fecha futura la deja programada."
                placeholder="Al publicar"
                valueFormat="DD/MM/YYYY"
                minDate={hoy}
                clearable
                {...contained}
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(fromDateValue(d))}
                error={errors.publicado_desde?.message}
              />
            )}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="publicado_hasta"
            control={control}
            render={({ field }) => (
              <>
                <DatePickerInput
                  label="Fecha de fin"
                  description="Obligatoria para publicar. Vale hasta el final de ese día."
                  placeholder="Elija la fecha"
                  valueFormat="DD/MM/YYYY"
                  minDate={toDateValue(desde) ?? hoy}
                  clearable
                  {...contained}
                  value={toDateValue(field.value)}
                  onChange={(d) => field.onChange(fromDateValue(d))}
                  error={errors.publicado_hasta?.message}
                />
                <AtajosFechaFin
                  desde={desde || hoyIso()}
                  valor={field.value}
                  onElegir={field.onChange}
                  etiqueta={desde ? 'Desde la fecha de inicio' : 'Desde hoy'}
                />
              </>
            )}
          />
        </Grid.Col>
      </Grid>

      <Controller
        name="fijado"
        control={control}
        render={({ field }) => (
          <Switch
            label="Fijar arriba en el Portal"
            description={`Sale antes que las demás. Hay ${MAXIMO_FIJADOS} lugares a la vez.`}
            checked={field.value}
            onChange={(e) => field.onChange(e.currentTarget.checked)}
          />
        )}
      />

      {tipo === 'comunicado' && (
        <Controller
          name="requiere_confirmacion"
          control={control}
          render={({ field }) => (
            <Switch
              label="Pedir confirmación de lectura"
              description="Cada servidor confirma que lo leyó; Talento Humano ve quién falta."
              checked={field.value}
              onChange={(e) => field.onChange(e.currentTarget.checked)}
            />
          )}
        />
      )}
    </Stack>
  )
}
