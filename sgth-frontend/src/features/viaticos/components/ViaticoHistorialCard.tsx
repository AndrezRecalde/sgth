'use client'

import { Stack, Text, Timeline } from '@mantine/core'
import { ESTADO_LABELS, TONO_VIATICO } from '../constants/viatico.constants'
import { SectionCard } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { formatFechaHora } from '@/lib/fecha'
import type { ViaticoHistorialEstado } from '@/types/api'

interface Props {
  historial: ViaticoHistorialEstado[]
}

/**
 * Quién movió el viático, cuándo y por qué. Un paso sin autor lo dio la tarea
 * programada que sigue las fechas del viaje. El motivo importa sobre todo al
 * servidor: una liquidación devuelta a corrección no dice qué corregir en
 * ningún otro lugar.
 */
export function ViaticoHistorialCard({ historial }: Props) {
  if (historial.length === 0) return null

  return (
    <SectionCard title="Historial" description="Quién movió el viático, cuándo y por qué">
      <Timeline active={historial.length - 1} bulletSize={14} lineWidth={2}>
        {historial.map((paso) => (
          <Timeline.Item
            key={paso.id}
            color={SEMANTIC_COLOR[TONO_VIATICO[paso.estado_nuevo] ?? 'neutral']}
            title={
              <Text size="sm" fw={500}>
                {ESTADO_LABELS[paso.estado_nuevo] ?? paso.estado_nuevo}
              </Text>
            }
          >
            <Stack gap={2}>
              <Text size="xs" c="dimmed">
                {formatFechaHora(paso.created_at)}
                {` · ${paso.usuario?.nombre_completo ?? 'Automático'}`}
              </Text>
              {paso.motivo && (
                <Text size="xs">
                  Motivo: {paso.motivo}
                </Text>
              )}
            </Stack>
          </Timeline.Item>
        ))}
      </Timeline>
    </SectionCard>
  )
}
