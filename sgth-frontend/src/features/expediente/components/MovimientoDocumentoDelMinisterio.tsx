'use client'

import { Button, Group, Select, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import type { MovimientoFormData } from '../schemas/movimiento.schema'

const ACTOS = [
  { value: 'decreto', label: 'Decreto' },
  { value: 'acuerdo', label: 'Acuerdo' },
  { value: 'resolucion', label: 'Resolución' },
]

/**
 * Lo que el documento con el formato del Ministerio pide además (fase 6.1):
 * la base legal que imprime la explicación y el decreto, acuerdo o resolución
 * que origina la acción.
 *
 * La base legal arranca vacía y se ve la de la clase como sugerencia: vacía,
 * el documento imprime la de la clase, y al suscribir queda congelada. Para
 * corregirla se copia y se edita; así no hace falta reescribirla al cambiar de
 * clase o de causal.
 */
export function MovimientoDocumentoDelMinisterio({
  form,
  baseDeLaClase,
}: {
  form: UseFormReturn<MovimientoFormData>
  /** La que pone la clase, o su causal si la tiene. */
  baseDeLaClase?: string | null
}) {
  const contained = useContainedInput()
  const { control, register, setValue, formState: { errors } } = form
  const base = useWatch({ control, name: 'base_legal' })

  return (
    <>
      <Textarea
        label="Base legal"
        placeholder={baseDeLaClase ?? 'La base legal del acto'}
        description="Vacía, el documento imprime la de la clase. Se congela al suscribir."
        autosize
        minRows={2}
        error={errors.base_legal?.message}
        {...contained}
        {...register('base_legal')}
      />
      {baseDeLaClase && !base?.trim() && (
        <Group justify="flex-end" mt={-6}>
          <Button size="compact-xs" variant="subtle" onClick={() => setValue('base_legal', baseDeLaClase, { shouldDirty: true })}>
            Copiar la de la clase para corregirla
          </Button>
        </Group>
      )}

      <Group grow align="flex-start">
        <Controller
          name="acto_origen_tipo"
          control={control}
          render={({ field }) => (
            <Select
              label="Acto que la origina"
              placeholder="Ninguno"
              data={ACTOS}
              clearable
              error={errors.acto_origen_tipo?.message}
              {...contained}
              value={field.value ?? null}
              onChange={(v) => field.onChange(v)}
            />
          )}
        />
        <TextInput
          label="Número del acto"
          placeholder="Opcional"
          error={errors.acto_origen_numero?.message}
          {...contained}
          {...register('acto_origen_numero')}
        />
        <Controller
          name="acto_origen_fecha"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha del acto"
              placeholder="Opcional"
              valueFormat="DD/MM/YYYY"
              clearable
              maxDate={new Date()}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d))}
              error={errors.acto_origen_fecha?.message}
              {...contained}
            />
          )}
        />
      </Group>
    </>
  )
}
