'use client'

import { DisponibilidadToggle } from
  '@/features/dispensario/components/DisponibilidadToggle'
import { useAuth } from '@/hooks/useAuth'
import { PageHeader, PageShell } from '@/components/ui'

export default function SaludHomePage() {
  const { usuario } = useAuth()
  const roles = (usuario?.roles as string[]) ?? []
  const esPersonalClinico = roles.some(r =>
    ['medico', 'odontologo'].includes(r)
  )

  return (
    <PageShell>
      <PageHeader
        title="Dispensario Médico"
        description="Sistema de Salud Ambulatoria — GADPE"
      />
      {esPersonalClinico && <DisponibilidadToggle />}
    </PageShell>
  )
}
