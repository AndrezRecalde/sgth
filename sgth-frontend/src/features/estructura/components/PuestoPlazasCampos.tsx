'use client'

import { Grid, NumberInput, Switch } from '@mantine/core'
import { Controller, type Control, type FieldErrors } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { PuestoFormData } from '../schemas/puesto.schema'

interface Props {
  control: Control<PuestoFormData>
  errors:  FieldErrors<PuestoFormData>
  /** Ocupado, no se desactiva (fase 7.1a). */
  ocupado: boolean
}

/** Cuántas plazas tiene el puesto, si es la jefatura de su unidad y si está activo. */
export function PuestoPlazasCampos({ control, errors, ocupado }: Props) {
  const contained = useContainedInput()

  return (
    <>
      <Grid.Col span={{ base: 12, sm: 4 }}>
        <Controller
          name="plazas"
          control={control}
          render={({ field }) => (
            <NumberInput
              label="Plazas"
              description="Una jefatura tiene una sola."
              placeholder="1"
              min={1}
              {...contained}
              value={field.value ?? 1}
              onChange={(v) => field.onChange(typeof v === 'number' ? v : 1)}
              error={errors.plazas?.message}
            />
          )}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 4 }}>
        <Controller
          name="es_jefe"
          control={control}
          render={({ field }) => (
            <Switch
              label="Es jefe de unidad"
              checked={field.value}
              onChange={(e) => field.onChange(e.currentTarget.checked)}
              mt="md"
            />
          )}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 4 }}>
        <Controller
          name="activo"
          control={control}
          render={({ field }) => (
            <Switch
              label="Puesto activo"
              description={ocupado ? 'Ocupado: no se desactiva.' : undefined}
              disabled={ocupado && field.value}
              checked={field.value}
              onChange={(e) => field.onChange(e.currentTarget.checked)}
              mt="md"
            />
          )}
        />
      </Grid.Col>
    </>
  )
}
