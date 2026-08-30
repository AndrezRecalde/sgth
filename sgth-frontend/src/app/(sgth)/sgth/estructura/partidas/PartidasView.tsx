'use client'

import { PageHeader, PageShell } from '@/components/ui'
import { PartidasPresupuestariasTab } from '@/features/estructura/components/PartidasPresupuestariasTab'

export function PartidasView() {
  return (
    <PageShell>
      <PageHeader
        title="Partidas presupuestarias"
        description="Partidas que sostienen las plazas del distributivo"
      />
      <PartidasPresupuestariasTab />
    </PageShell>
  )
}
