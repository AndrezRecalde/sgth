'use client'

import { PageHeader, PageShell } from '@/components/ui'
import { PermisosTab } from '@/features/asistencia/components/PermisosTab'

export function PermisosView() {
  return (
    <PageShell>
      <PageHeader
        title="Permisos"
        description="Solicitudes de permiso: registro, aprobación y anulación"
      />
      <PermisosTab />
    </PageShell>
  )
}
