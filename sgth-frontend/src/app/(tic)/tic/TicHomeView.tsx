'use client'

import { IconDevices } from '@tabler/icons-react'
import { EmptyState, PageHeader, PageShell } from '@/components/ui'

/**
 * Inicio del subsistema Tecnologías.
 *
 * Por ahora no hay nada que resumir: el tablero (fase 4 del diseño) se arma
 * con los datos del inventario, los préstamos y la mesa de ayuda, que llegan
 * antes. Mientras tanto la pantalla dice qué viene, en vez de quedar en blanco.
 */
export function TicHomeView() {
  return (
    <PageShell>
      <PageHeader
        title="Tecnologías"
        description="Unidad de Tecnologías de la Información y Comunicación"
      />
      <EmptyState
        icon={IconDevices}
        title="El tablero de Tecnologías se está preparando"
        description="Aquí verá el inventario de equipos, los préstamos y los tickets de la mesa de ayuda a medida que se habiliten."
      />
    </PageShell>
  )
}
