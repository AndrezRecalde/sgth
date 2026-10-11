'use client'

import { Grid, Select } from '@mantine/core'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { MovimientoFormData } from '../schemas/movimiento.schema'

const PRORROGAS = [
  { value: 'enfermedad_catastrofica', label: 'Enfermedad catastrófica' },
  { value: 'calamidad_domestica', label: 'Calamidad doméstica' },
]

/**
 * La licencia que concede el Consejo Provincial (COOTAD Art. 47 s; fase 4.1b):
 * con o sin remuneración, como diga su resolución, y la causa de la prórroga
 * cuando el Consejo la extiende más allá de los 60 días del año.
 */
export function MovimientoDatosLicenciaConsejo({ form }: { form: UseFormReturn<MovimientoFormData> }) {
  const contained = useContainedInput()
  const { control, formState: { errors } } = form

  return (
    <Grid gap="sm">
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="con_remuneracion"
          control={control}
          render={({ field }) => (
            <Select
              label="Remuneración"
              description="Lo dice la resolución del Consejo."
              placeholder="Seleccionar"
              withAsterisk
              data={[
                { value: 'con', label: 'Con remuneración' },
                { value: 'sin', label: 'Sin remuneración' },
              ]}
              value={field.value == null ? null : field.value ? 'con' : 'sin'}
              onChange={(v) => field.onChange(v == null ? null : v === 'con')}
              error={errors.con_remuneracion?.message}
              {...contained}
            />
          )}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="prorroga_causa"
          control={control}
          render={({ field }) => (
            <Select
              label="Prórroga del Consejo"
              description="Solo si pasa de los 60 días del año."
              placeholder="Sin prórroga"
              clearable
              data={PRORROGAS}
              value={field.value ?? null}
              onChange={(v) => field.onChange(v)}
              error={errors.prorroga_causa?.message}
              {...contained}
            />
          )}
        />
      </Grid.Col>
    </Grid>
  )
}
