'use client'

import { Group, Text } from '@mantine/core'
import { IconCircleCheck, IconCircleDashed, IconCircleX } from '@tabler/icons-react'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import type { ItemOrdenExamen } from '../../services/examenService'

const MARCA: Record<ItemOrdenExamen['estado'], { icono: typeof IconCircleCheck; color: string; titulo: string }> = {
  pendiente:    { icono: IconCircleDashed, color: 'dimmed',               titulo: 'Pendiente' },
  recibido:     { icono: IconCircleCheck,  color: SEMANTIC_COLOR.success, titulo: 'Recibido' },
  no_realizado: { icono: IconCircleX,      color: 'dimmed',               titulo: 'No realizado' },
}

/** Los exámenes de una orden, cada uno con cómo va: pendiente, recibido o no realizado. */
export function ItemsDeOrden({ items }: { items: ItemOrdenExamen[] }) {
  return (
    <Group gap="xs" wrap="wrap">
      {items.map((i) => {
        const { icono: Icono, color, titulo } = MARCA[i.estado]
        const nombre = i.examen?.nombre ?? 'Examen retirado del catálogo'

        return (
          <Group key={i.id} gap={3} wrap="nowrap"
            title={i.motivo_no_realizado ? `${titulo}: ${i.motivo_no_realizado}` : titulo}>
            <Text span c={color} lh={0} aria-label={titulo}><Icono size={13} /></Text>
            <Text size="xs" td={i.estado === 'no_realizado' ? 'line-through' : undefined}
              c={i.estado === 'no_realizado' ? 'dimmed' : undefined}>
              {nombre}{i.nota ? ` (${i.nota})` : ''}
            </Text>
          </Group>
        )
      })}
    </Group>
  )
}
