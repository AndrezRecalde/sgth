'use client'

import { Text, Timeline } from '@mantine/core'
import { IconCheck, IconClock } from '@tabler/icons-react'
import { DataState } from '@/components/ui'
import type { MarcacionBiometrica } from '@/types/api'
import { horaDe, type AccionMarcacion } from '../utils/marcacionOnline'

interface Props {
  estado: MarcacionBiometrica | null | undefined
  acciones: AccionMarcacion[]
  siguiente: AccionMarcacion | null
  cargando: boolean
  error: unknown
  onReintentar: () => void
}

/**
 * Las marcaciones de hoy en orden. Si el biométrico no responde se dice, en
 * vez de pintar «--:--» como si la persona no hubiera marcado.
 */
export function ProgresoHoy({ estado, acciones, siguiente, cargando, error, onReintentar }: Props) {
  // El tramo resaltado llega hasta la última acción con hora, aunque falte
  // alguna anterior (una entrada que no se marcó).
  const ultimaRegistrada = acciones.reduce((ultima, a, i) => (horaDe(estado, a.clave) ? i : ultima), -1)

  return (
    <DataState
      loading={cargando}
      error={error}
      errorTitle="No se pudo consultar el progreso de hoy"
      errorHint="No quiere decir que no haya marcado: el biométrico no respondió."
      onRetry={onReintentar}
      skeletonRows={acciones.length}
    >
      <Timeline active={ultimaRegistrada} bulletSize={24} lineWidth={2}>
        {acciones.map((accion) => {
          const hora = horaDe(estado, accion.clave)
          const esSiguiente = siguiente?.clave === accion.clave

          return (
            <Timeline.Item
              key={accion.clave}
              title={accion.etiqueta}
              bullet={hora ? <IconCheck size={14} /> : <IconClock size={14} />}
            >
              <Text size="xs" c="dimmed" mt={4}>
                {hora ?? (esSiguiente ? 'Pendiente: es la siguiente' : 'Pendiente')}
              </Text>
            </Timeline.Item>
          )
        })}
      </Timeline>
    </DataState>
  )
}
