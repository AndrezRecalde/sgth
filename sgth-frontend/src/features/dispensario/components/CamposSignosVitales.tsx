'use client'

import { NumberInput, SimpleGrid, Stack } from '@mantine/core'
import { Controller, type Control, type FieldErrors } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { SectionCard } from '@/components/ui'
import { rangoSinAlerta } from '../constants/signosVitales'
import type { SignosVitalesFormData } from '../schemas/signosVitales.schema'

interface Props {
  control: Control<SignosVitalesFormData>
  errors:  FieldErrors<SignosVitalesFormData>
}

type CampoObligatorio =
  | 'presion_sistolica' | 'presion_diastolica'
  | 'frecuencia_cardiaca' | 'frecuencia_respiratoria'
  | 'temperatura_c' | 'saturacion_oxigeno'

/**
 * Las constantes, con su unidad en la etiqueta. Las presiones y frecuencias
 * son enteras: sin `allowDecimal={false}`, un 120,5 llegaba al esquema y
 * fallaba con un mensaje que no explicaba nada.
 */
const CAMPOS: { name: CampoObligatorio; label: string; decimales?: number }[] = [
  { name: 'presion_sistolica',       label: 'P. sistólica (mmHg)' },
  { name: 'presion_diastolica',      label: 'P. diastólica (mmHg)' },
  { name: 'frecuencia_cardiaca',     label: 'Frec. cardíaca (lpm)' },
  { name: 'frecuencia_respiratoria', label: 'Frec. respiratoria (rpm)' },
  { name: 'temperatura_c',           label: 'Temperatura (°C)', decimales: 1 },
  { name: 'saturacion_oxigeno',      label: 'Sat. oxígeno (%)', decimales: 1 },
]

/**
 * La sección «Signos vitales», la misma en el triaje de un turno y en el
 * previo al FEMO. Antes el triaje la repetía campo por campo, con otras
 * etiquetas, y el texto de ayuda de los dos no coincidía con la alerta.
 */
export function CamposSignosVitales({ control, errors }: Props) {
  const contained = useContainedInput()

  return (
    <SectionCard title="Signos vitales">
      <Stack gap="md">
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          {CAMPOS.map((c) => (
            <Controller
              key={c.name}
              name={c.name}
              control={control}
              render={({ field }) => (
                <NumberInput
                  label={c.label}
                  description={rangoSinAlerta(c.name)}
                  decimalScale={c.decimales}
                  allowDecimal={!!c.decimales}
                  hideControls
                  {...contained}
                  value={field.value ?? ''}
                  onChange={(v) => field.onChange(v === '' ? undefined : Number(v))}
                  error={errors[c.name]?.message}
                />
              )}
            />
          ))}
        </SimpleGrid>

        <Controller
          name="glucosa"
          control={control}
          render={({ field }) => (
            <NumberInput
              label="Glucosa (mg/dL)"
              decimalScale={1}
              description={`Opcional. ${rangoSinAlerta('glucosa')}`}
              hideControls
              {...contained}
              value={field.value ?? ''}
              onChange={(v) => field.onChange(v === '' ? null : Number(v))}
              error={errors.glucosa?.message}
            />
          )}
        />
      </Stack>
    </SectionCard>
  )
}
