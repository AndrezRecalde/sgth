'use client'

import { PageShell } from '@/components/ui'
import { PermisoPorFolio } from '@/features/asistencia/components/PermisoPorFolio'

interface Props {
  folio: string
}

export function PermisoPorFolioView({ folio }: Props) {
  return (
    <PageShell>
      <PermisoPorFolio folio={folio} />
    </PageShell>
  )
}
