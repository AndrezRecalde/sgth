'use client'

import { PageHeader, PageShell } from '@/components/ui'
import { MarcacionOnlineTab } from '@/features/asistencia/components/MarcacionOnlineTab'

export function MarcacionOnlineView() {
  return (
    <PageShell>
      <PageHeader
        title="Marcación online"
        description="Registro de entrada y salida desde el navegador, con ubicación"
      />
      <MarcacionOnlineTab />
    </PageShell>
  )
}
