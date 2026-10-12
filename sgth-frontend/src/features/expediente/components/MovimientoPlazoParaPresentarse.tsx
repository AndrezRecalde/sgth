'use client'

import { Select, Text, TextInput } from '@mantine/core'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { ClaseDelCatalogo } from '@/types/api'
import type { MovimientoFormData } from '../schemas/movimiento.schema'
import { fijaTerminoParaPresentarse, presentaEnVeinticuatroHoras } from '../utils/plazoParaPresentarse'

const DIAS = [
  { value: '3', label: '3 días hábiles' },
  { value: '4', label: '4 días laborables' },
  { value: '5', label: '5 días laborables' },
]

/**
 * El término para presentarse del ingreso (Reglamento a la LOSEP, Art. 20;
 * fase 6.4): 3 días hábiles desde el registro, o hasta 5 si lo piden las
 * circunstancias geográficas, que se dicen. El ocasional tiene 24 horas desde
 * el registro del contrato, y no se elige.
 */
export function MovimientoPlazoParaPresentarse({
  form,
  clase,
}: {
  form: UseFormReturn<MovimientoFormData>
  clase: ClaseDelCatalogo | undefined
}) {
  const contained = useContainedInput()
  const { control, register, formState: { errors } } = form
  const [nombramiento, dias] = useWatch({ control, name: ['tipo_nombramiento_propuesto', 'dias_para_presentarse'] })

  if (presentaEnVeinticuatroHoras(clase, nombramiento)) {
    return (
      <Text size="sm" c="dimmed">
        El contratado tiene 24 horas desde el registro del contrato para presentarse a laborar; si no, el contrato
        se termina (Reglamento a la LOSEP, Art. 20).
      </Text>
    )
  }

  if (!fijaTerminoParaPresentarse(clase, nombramiento)) return null

  return (
    <>
      <Controller
        name="dias_para_presentarse"
        control={control}
        render={({ field }) => (
          <Select
            label="Término para presentarse"
            description="Desde el registro, o desde que rige si es después. Sin presentarse, el nombramiento queda insubsistente (Reglamento a la LOSEP, Art. 20)."
            data={DIAS}
            allowDeselect={false}
            value={String(field.value ?? 3)}
            onChange={(v) => field.onChange(Number(v ?? 3))}
            {...contained}
          />
        )}
      />
      {(dias ?? 3) > 3 && (
        <TextInput
          label="Circunstancias geográficas"
          description="Por qué necesita más de 3 días para llegar."
          withAsterisk
          error={errors.razon_dias_adicionales?.message}
          {...contained}
          {...register('razon_dias_adicionales')}
        />
      )}
    </>
  )
}
