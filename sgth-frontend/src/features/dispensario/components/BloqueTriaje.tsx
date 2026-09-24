'use client'

import { Group, Stack, Text } from '@mantine/core'
import { SectionHeading } from '@/components/ui'
import type { Triaje } from '../services/triajeService'

function CampoTriaje({
  label, valor,
}: {
  label: string
  valor?: string | number | null
}) {
  // El 0 es un valor, no un hueco: una saturación de 0 no se esconde.
  if (!valor && valor !== 0) return null

  return (
    <Group justify="space-between" py={1}>
      <Text size="xs" c="dimmed">{label}</Text>
      <Text size="xs" fw={500}>{valor}</Text>
    </Group>
  )
}

/** Los signos vitales del triaje, en el panel de contexto. Sin triaje, nada. */
export function BloqueTriaje({ triaje }: { triaje?: Triaje | null }) {
  if (!triaje) return null

  return (
    <>
      <SectionHeading title="Triaje" />
      <Stack gap={0}>
        <CampoTriaje label="Peso"  valor={triaje.peso_kg ? `${triaje.peso_kg} kg` : null} />
        <CampoTriaje label="Talla" valor={triaje.talla_cm ? `${triaje.talla_cm} cm` : null} />
        <CampoTriaje label="IMC"   valor={triaje.imc} />
        <CampoTriaje
          label="P. arterial"
          valor={triaje.presion_sistolica
            ? `${triaje.presion_sistolica}/${triaje.presion_diastolica}`
            : null}
        />
        <CampoTriaje
          label="F. cardíaca"
          valor={triaje.frecuencia_cardiaca ? `${triaje.frecuencia_cardiaca} bpm` : null}
        />
        <CampoTriaje
          label="F. respiratoria"
          valor={triaje.frecuencia_respiratoria ? `${triaje.frecuencia_respiratoria} rpm` : null}
        />
        <CampoTriaje
          label="Temperatura"
          valor={triaje.temperatura_c ? `${triaje.temperatura_c} °C` : null}
        />
        <CampoTriaje
          label="Sat. O2"
          valor={triaje.saturacion_oxigeno ? `${triaje.saturacion_oxigeno}%` : null}
        />
      </Stack>
    </>
  )
}
