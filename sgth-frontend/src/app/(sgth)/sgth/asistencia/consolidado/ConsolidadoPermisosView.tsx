'use client'

import { PageHeader, PageShell } from '@/components/ui'
import { ConsolidadoPermisosTab } from '@/features/asistencia/components/ConsolidadoPermisosTab'

export function ConsolidadoPermisosView() {
  return (
    <PageShell>
      <PageHeader
        title="Consolidado de permisos"
        description="Permisos por tipo y rango de fechas, exportable a Excel y PDF"
      />
      <ConsolidadoPermisosTab />
    </PageShell>
  )
}
