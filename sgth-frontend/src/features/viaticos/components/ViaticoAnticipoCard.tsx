'use client'

import { Text } from '@mantine/core'
import { DetailList, SectionCard, type DetailItem } from '@/components/ui'
import { MODALIDAD_LABELS } from '../constants/viatico.constants'
import { dolares } from '../utils/monto'
import type { ViaticoConRelaciones } from '@/types/api'

const TERMINADOS = ['cancelado', 'rechazado']

interface Props {
  viatico: ViaticoConRelaciones
}

/** Cuánto le corresponde al servidor, cuánto se le adelanta y con qué respaldo. */
export function ViaticoAnticipoCard({ viatico: d }: Props) {
  const sinAnticipo = d.modalidad_anticipo === 'sin_anticipo'

  const items: DetailItem[] = [
    {
      label: 'Monto del viático',
      value: <Text size="lg" fw={700}>{dolares(d.monto_calculado)}</Text>,
      ancho: true,
    },
    { label: 'Modalidad', value: MODALIDAD_LABELS[d.modalidad_anticipo ?? ''] ?? d.modalidad_anticipo },
    {
      label: 'Anticipo',
      // El monto del anticipo se fija al entregarlo: antes de eso salía «$0.00».
      value: sinAnticipo
        ? 'No se entrega'
        : Number(d.monto_anticipo ?? 0) > 0
          ? dolares(d.monto_anticipo)
          : TERMINADOS.includes(String(d.estado)) ? 'No se entregó' : 'Por entregar',
    },
    // El respaldo que asigna Financiero; se imprime en el comprobante.
    { label: 'Resolución', value: d.numero_resolucion },
    { label: 'Partida presupuestaria', value: d.partida_presupuestaria?.codigo },
  ]

  return (
    <SectionCard title="Anticipo y monto">
      <DetailList items={items} />
    </SectionCard>
  )
}
