'use client'

import { Group, Stack, Text } from '@mantine/core'
import { SectionHeading, StatusBadge } from '@/components/ui'
import { formatFechaHora } from '@/lib/fecha'
import type { SancionDisciplinaria } from '@/types/api'
import { TIPO_SANCION_LABELS } from '../utils/etiquetas'

/**
 * Las sanciones que quedaron sin efecto porque se anuló su acción de personal
 * (fase 2.5). No se borran: el sumario las conserva como constancia, y si
 * estaba resuelto volvió a «Con informe» para dictar la correcta.
 */
export function SancionesSinEfecto({ sanciones }: { sanciones: SancionDisciplinaria[] }) {
  if (!sanciones.length) return null

  return (
    <div>
      <SectionHeading title="Sanciones sin efecto" mb="xs" />
      <Stack gap="sm">
        {sanciones.map((s) => (
          <div key={s.id}>
            <Group gap="xs">
              <StatusBadge>{TIPO_SANCION_LABELS[s.tipo_sancion]}</StatusBadge>
              <Text size="sm" c="dimmed">
                Sin efecto desde el {formatFechaHora(s.anulada_en)}
                {s.movimiento_personal?.codigo_registro
                  ? ` · acción ${s.movimiento_personal.codigo_registro}`
                  : ''}
              </Text>
            </Group>
            {s.motivo_anulacion && (
              <Text size="sm" mt={4}>{s.motivo_anulacion}</Text>
            )}
          </div>
        ))}
      </Stack>
    </div>
  )
}
