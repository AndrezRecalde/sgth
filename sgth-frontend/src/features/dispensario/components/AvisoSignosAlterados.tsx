'use client'

import { Alert, Group, Stack, Text } from '@mantine/core'
import { useWatch, type Control } from 'react-hook-form'
import { IconAlertTriangle } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { hallazgos, NIVEL_ALERTA } from '../constants/signosVitales'
import type { SignosVitalesFormData } from '../schemas/signosVitales.schema'

interface Props {
  control: Control<SignosVitalesFormData>
  /** Menor de `EDAD_ADULTO`: el backend no valora, así que no se promete alerta. */
  esMenor: boolean
  /** Qué pasa con la alerta al guardar. El triaje marca la cola; el SSO no. */
  consecuencia: { critico: string; atencion: string }
}

const VIGILADOS = [
  'presion_sistolica', 'presion_diastolica', 'frecuencia_cardiaca',
  'frecuencia_respiratoria', 'temperatura_c', 'saturacion_oxigeno', 'glucosa',
] as const

/**
 * El aviso de constantes fuera de rango mientras se escriben: tiene que llegar
 * con el paciente delante, no al guardar. El nivel que queda registrado lo
 * decide el backend; esto solo lo adelanta.
 *
 * Vigila solo las siete constantes: con `useWatch({ control })` el formulario
 * entero se volvía a pintar en cada tecla.
 */
export function AvisoSignosAlterados({ control, esMenor, consecuencia }: Props) {
  const valores = useWatch({ control, name: [...VIGILADOS] })
  const alterados = hallazgos(
    Object.fromEntries(VIGILADOS.map((campo, i) => [campo, valores[i]])),
  )

  if (alterados.length === 0) return null

  // A un menor los rangos de adulto no le aplican y el backend guarda «Sin
  // valorar». Antes se le anunciaba «quedará marcado como crítico» y no
  // quedaba marcado nada.
  if (esMenor) {
    return (
      <Alert color="slate" variant="light" radius="md" title="Paciente menor de edad">
        <Text size="xs">
          Hay cifras fuera del rango de un adulto, pero en menores el sistema
          no las valora: el triaje quedará «Sin valorar». Si le preocupan,
          avise al profesional directamente.
        </Text>
      </Alert>
    )
  }

  const hayCritico = alterados.some((h) => h.nivel === 'critico')

  return (
    <Alert
      icon={<IconAlertTriangle size={16} />}
      color={hayCritico ? 'red' : 'amber'}
      variant="light"
      radius="md"
      title={hayCritico ? 'Signos vitales críticos' : 'Signos vitales fuera de rango'}
    >
      <Stack gap={6}>
        {alterados.map((h) => (
          <Group key={h.campo} gap="xs" wrap="nowrap">
            <StatusBadge tone={NIVEL_ALERTA[h.nivel].tono} size="xs">
              {NIVEL_ALERTA[h.nivel].etiqueta}
            </StatusBadge>
            <Text size="xs">
              {h.etiqueta}: <strong>{h.valor}</strong>
            </Text>
          </Group>
        ))}
        <Text size="xs" c="dimmed">
          {hayCritico ? consecuencia.critico : consecuencia.atencion}
        </Text>
      </Stack>
    </Alert>
  )
}
