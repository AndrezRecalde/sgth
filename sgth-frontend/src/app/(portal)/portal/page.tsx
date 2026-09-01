import type { Metadata } from 'next'
import { PortalHomeView } from '@/features/portal/components/PortalHomeView'
import { PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Portal del Servidor',
  description: 'Perfil y noticias institucionales — GAD Provincial de Esmeraldas',
}

export default function PortalHomePage() {
  return (
    <PageShell>
      <PortalHomeView />
    </PageShell>
  )
}
