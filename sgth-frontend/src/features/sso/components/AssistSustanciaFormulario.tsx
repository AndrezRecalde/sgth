'use client'

import { Stack, Title, Text, Select } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { RespuestaSustanciaAssist, SustanciaAssistInfo } from '../services/assistService'

interface Props {
  sustancia: SustanciaAssistInfo
  opcionesFrecuencia3m: Record<string, string>
  opcionesFrecuenciaVida: Record<string, string>
  value: Partial<RespuestaSustanciaAssist>
  onChange: (value: Partial<RespuestaSustanciaAssist>) => void
}

/** Sub-formulario ASSIST para una sustancia: aplica la lógica de "preguntas filtro" del
 * manual (P2 determina si se preguntan P3-P5; P6-P7 siempre se preguntan). */
export function AssistSustanciaFormulario({ sustancia, opcionesFrecuencia3m, opcionesFrecuenciaVida, value, onChange }: Props) {
  const contained = useContainedInput()

  const opcionesSelect = (opciones: Record<string, string>) =>
    Object.entries(opciones).map(([v, label]) => ({ value: v, label }))

  const consumioUltimos3Meses = !!value.p2 && value.p2 !== 'nunca'

  return (
    <Stack gap="sm">
      <div>
        <Title order={4}>{sustancia.etiqueta}</Title>
        <Text size="xs" c="dimmed">{sustancia.ejemplos}</Text>
      </div>

      <Select
        label="En los últimos 3 meses, ¿con qué frecuencia ha consumido esta sustancia?"
        data={opcionesSelect(opcionesFrecuencia3m)}
        required
        {...contained}
        value={value.p2 ?? null}
        onChange={(v) => onChange({ ...value, p2: v ?? undefined })}
      />

      {consumioUltimos3Meses && (
        <>
          <Select
            label="En los últimos 3 meses, ¿con qué frecuencia ha sentido un fuerte deseo o ansias de consumirla?"
            data={opcionesSelect(opcionesFrecuencia3m)}
            required
            {...contained}
            value={value.p3 ?? null}
            onChange={(v) => onChange({ ...value, p3: v ?? undefined })}
          />
          <Select
            label="En los últimos 3 meses, ¿con qué frecuencia el consumo le ha causado problemas de salud, sociales, legales o económicos?"
            data={opcionesSelect(opcionesFrecuencia3m)}
            required
            {...contained}
            value={value.p4 ?? null}
            onChange={(v) => onChange({ ...value, p4: v ?? undefined })}
          />
          {sustancia.incluye_pregunta_5 && (
            <Select
              label="En los últimos 3 meses, ¿con qué frecuencia dejó de hacer lo que habitualmente se esperaba de usted por el consumo?"
              data={opcionesSelect(opcionesFrecuencia3m)}
              required
              {...contained}
              value={value.p5 ?? null}
              onChange={(v) => onChange({ ...value, p5: v ?? undefined })}
            />
          )}
        </>
      )}

      <Select
        label="¿Un amigo, familiar o alguien más ha mostrado alguna vez preocupación por sus hábitos de consumo?"
        data={opcionesSelect(opcionesFrecuenciaVida)}
        required
        {...contained}
        value={value.p6 ?? null}
        onChange={(v) => onChange({ ...value, p6: v ?? undefined })}
      />
      <Select
        label="¿Ha intentado alguna vez reducir o eliminar el consumo y no lo ha logrado?"
        data={opcionesSelect(opcionesFrecuenciaVida)}
        required
        {...contained}
        value={value.p7 ?? null}
        onChange={(v) => onChange({ ...value, p7: v ?? undefined })}
      />
    </Stack>
  )
}
