'use client'

import { Alert, Group, Stack, Text } from '@mantine/core'
import { IconHistory } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { useHistorialTriaje } from '../hooks/useTriaje'
import { NIVEL_ALERTA } from '../constants/signosVitales'

interface Props {
  agendaId: number
}

function hora(fecha?: string | null): string {
  if (!fecha) return '—'
  return new Date(fecha).toLocaleTimeString('es-EC', {
    hour: '2-digit', minute: '2-digit',
  })
}

/**
 * Las tomas ya registradas de este turno.
 *
 * Se muestra al rehacer un triaje: quien corrige tiene que ver con qué cifras
 * estaba antes. Ninguna se pierde —cada toma es una fila propia— y la vigente
 * pasa a ser la última.
 */
export function TomasPreviasTriaje({ agendaId }: Props) {
  const { data: tomas = [] } = useHistorialTriaje(agendaId)

  if (tomas.length === 0) return null

  return (
    <Alert
      icon={<IconHistory size={16} />}
      color="blue"
      variant="light"
      radius="md"
      title={
        tomas.length === 1
          ? 'Ya hay una toma registrada'
          : `Ya hay ${tomas.length} tomas registradas`
      }
    >
      <Stack gap={6}>
        {tomas.map((toma) => (
          <Group key={toma.id} gap="xs" wrap="wrap">
            <Text size="xs" ff="monospace" c="dimmed">
              {hora(toma.registrado_en)}
            </Text>
            <Text size="xs">
              {toma.presion_sistolica}/{toma.presion_diastolica} mmHg
              {' · '}{toma.frecuencia_cardiaca} lpm
              {' · '}{toma.temperatura_c} °C
              {' · '}Sat {toma.saturacion_oxigeno} %
            </Text>
            {toma.nivel_alerta && toma.nivel_alerta !== 'normal' && (
              <StatusBadge tone={NIVEL_ALERTA[toma.nivel_alerta].tono} size="xs">
                {NIVEL_ALERTA[toma.nivel_alerta].etiqueta}
              </StatusBadge>
            )}
          </Group>
        ))}
        <Text size="xs" c="dimmed">
          Al guardar se añade una toma nueva y pasa a ser la vigente. Las
          anteriores se conservan.
        </Text>
      </Stack>
    </Alert>
  )
}
