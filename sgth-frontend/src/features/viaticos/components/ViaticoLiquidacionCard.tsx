'use client'

import { Stack, Text } from '@mantine/core'
import { SectionCard } from '@/components/ui'
import { LiquidacionSection } from './LiquidacionSection'
import { CalculoViaticoCard } from './CalculoViaticoCard'
import { LiquidacionActividadesCard } from './LiquidacionActividadesCard'
import { RevisionComprobantes } from './RevisionComprobantes'
import type { ViaticoConRelaciones } from '@/types/api'

interface Props {
  viatico: ViaticoConRelaciones
  estadoActual: string
  onSuccess: () => void
}

/*
| La liquidación en la ficha del viático: se presenta mientras está pendiente y
| se consulta después.
|
| La cuenta la resuelve el backend y llega en `viatico.calculo`.
*/

export function ViaticoLiquidacionCard({ viatico: d, estadoActual, onSuccess }: Props) {
  const presentando = estadoActual === 'pendiente_liquidacion'

  return (
    <SectionCard
      title="Liquidación"
      description={
        presentando
          ? 'Las actividades y los comprobantes del viaje'
          : 'Lo que se presentó y cómo lo revisó Financiero'
      }
    >
      {presentando ? (
        <LiquidacionSection viatico={d} onSuccess={onSuccess} />
      ) : d.liquidacion ? (
        <Stack gap="md">
          {d.calculo && <CalculoViaticoCard calculo={d.calculo} />}

          {(d.liquidacion.actividades?.length ?? 0) > 0 && (
            <LiquidacionActividadesCard
              actividades={(d.liquidacion.actividades ?? []).map((a) => ({
                fecha:       String(a.fecha ?? ''),
                hora_inicio: String(a.hora_inicio ?? ''),
                hora_fin:    String(a.hora_fin ?? ''),
                descripcion: String(a.descripcion ?? ''),
                lugar:       String(a.lugar ?? ''),
              }))}
            />
          )}

          <RevisionComprobantes viatico={d} />
        </Stack>
      ) : (
        <Text size="sm" c="dimmed">Todavía no se registra la liquidación.</Text>
      )}
    </SectionCard>
  )
}
