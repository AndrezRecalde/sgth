'use client'

import { Button } from '@mantine/core'
import { IconRoute } from '@tabler/icons-react'
import { SectionCard } from '@/components/ui'
import { TramosList } from './TramosList'
import type { ViaticoConRelaciones } from '@/types/api'

interface Props {
  viatico: ViaticoConRelaciones
  puedeEditar: boolean
  onGestionar: () => void
}

/**
 * Los tramos del viaje. Sin tramos se ve un solo mensaje: antes salían dos a
 * la vez, el de la lista y un aviso ámbar que repetía lo mismo.
 */
export function ViaticoItinerarioCard({ viatico: d, puedeEditar, onGestionar }: Props) {
  return (
    <SectionCard
      title="Itinerario"
      description="Cada tramo del viaje, de ida y de regreso"
      actions={
        puedeEditar && (
          <Button variant="subtle" size="xs" leftSection={<IconRoute size={14} />} onClick={onGestionar}>
            Editar itinerario
          </Button>
        )
      }
    >
      <TramosList viaticoId={d.id} puedeEditar={false} />
    </SectionCard>
  )
}
