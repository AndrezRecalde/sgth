'use client'

import { Grid, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { CompletarVinculoFormData } from '../schemas/completarVinculo.schema'
import { formatFecha, fromDateValueOrNull, toDateValue } from '@/lib/fecha'

/**
 * El plazo del vínculo que nace.
 *
 * El inicio no se elige: es la fecha en que rige la acción, y cambiarla exigiría
 * anularla y registrar otra. El término solo existe donde se pacta —servicios
 * ocasionales y profesionales—; en los nombramientos se dice que no lleva plazo
 * en vez de dejar un calendario que no aplica.
 */
export function CompletarVinculoPlazo({
  form,
  fechaEfectiva,
  llevaPlazo,
}: {
  form: UseFormReturn<CompletarVinculoFormData>
  fechaEfectiva?: string | null
  llevaPlazo: boolean
}) {
  const contained = useContainedInput()
  const { control, formState: { errors } } = form

  return (
    <Grid>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Fecha de inicio"
          description="Es la fecha en que rige la acción; para cambiarla se anula y se registra otra."
          value={formatFecha(fechaEfectiva)}
          readOnly
          {...contained}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        {llevaPlazo ? (
          <Controller
            name="fecha_fin_propuesta"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha de término"
                description="Servicios Profesionales toma el 31 de diciembre de su año si se deja vacío."
                valueFormat="DD/MM/YYYY"
                clearable
                // El término no puede ser anterior al inicio: pasaba y el
                // contrato nacía con el fin antes que el principio.
                minDate={toDateValue(fechaEfectiva) ?? undefined}
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                error={errors.fecha_fin_propuesta?.message}
                {...contained}
              />
            )}
          />
        ) : (
          <TextInput
            label="Fecha de término"
            description="Este nombramiento no lleva plazo."
            value="Sin plazo"
            readOnly
            {...contained}
          />
        )}
      </Grid.Col>
    </Grid>
  )
}
