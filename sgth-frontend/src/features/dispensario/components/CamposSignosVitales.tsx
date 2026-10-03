'use client'

import { NumberInput, SimpleGrid, Stack } from '@mantine/core'
import { Controller, type Control, type FieldErrors } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { SectionCard } from '@/components/ui'
import type { SolicitudSignosVitalesFormData } from '../schemas/solicitudSignosVitales.schema'

interface Props {
  control: Control<SolicitudSignosVitalesFormData>
  errors:  FieldErrors<SolicitudSignosVitalesFormData>
}

type CampoObligatorio =
  | 'presion_sistolica' | 'presion_diastolica'
  | 'frecuencia_cardiaca' | 'frecuencia_respiratoria'
  | 'temperatura_c' | 'saturacion_oxigeno'

/** Las constantes del triaje, con su unidad en la etiqueta y su rango normal. */
const CAMPOS: { name: CampoObligatorio; label: string; description: string; decimales?: number }[] = [
  { name: 'presion_sistolica',       label: 'P. sistólica (mmHg)',      description: 'Normal: 90–120 mmHg' },
  { name: 'presion_diastolica',      label: 'P. diastólica (mmHg)',     description: 'Normal: 60–80 mmHg' },
  { name: 'frecuencia_cardiaca',     label: 'Frec. cardíaca (lpm)',     description: 'Normal: 60–100 lpm' },
  { name: 'frecuencia_respiratoria', label: 'Frec. respiratoria (rpm)', description: 'Normal: 12–20 rpm' },
  { name: 'temperatura_c',           label: 'Temperatura (°C)',         description: 'Normal: 36.1–37.2 °C', decimales: 1 },
  { name: 'saturacion_oxigeno',      label: 'Sat. oxígeno (%)',         description: 'Normal: 95–100 %',     decimales: 1 },
]

/** La sección «Signos vitales» del triaje previo al FEMO. */
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
                  description={c.description}
                  decimalScale={c.decimales}
                  allowDecimal={!!c.decimales}
                  hideControls
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(Number(v) || undefined)}
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
              description="Opcional. Normal en ayunas: 70–100 mg/dL"
              hideControls
              {...contained}
              value={field.value ?? undefined}
              onChange={(v) => field.onChange(v ? Number(v) : null)}
            />
          )}
        />
      </Stack>
    </SectionCard>
  )
}
