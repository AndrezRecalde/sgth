'use client'

import { IconSpeakerphone } from '@tabler/icons-react'
import { EmptyState, PageHeader, PageShell } from '@/components/ui'

/**
 * Inicio del subsistema Comunicación Social.
 *
 * Por ahora no hay nada que resumir: el avance (fotos por unidad, credenciales
 * por imprimir o desactualizadas) llega con la fase 3 del diseño, cuando ya
 * existan las fotos y las credenciales. Mientras tanto la pantalla dice qué
 * viene, en vez de quedar en blanco.
 */
export function ComunicacionHomeView() {
  return (
    <PageShell>
      <PageHeader
        title="Comunicación Social"
        description="Fotografías de los servidores, credenciales e identidad institucional"
      />
      <EmptyState
        icon={IconSpeakerphone}
        title="El inicio de Comunicación se está preparando"
        description="Aquí verá las fotografías de los servidores, sus credenciales y los logos de la institución a medida que se habiliten."
      />
    </PageShell>
  )
}
