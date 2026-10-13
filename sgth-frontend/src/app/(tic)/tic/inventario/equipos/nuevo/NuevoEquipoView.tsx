'use client'

import { useRouter } from 'next/navigation'
import { PageHeader, PageShell } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { BienForm } from '@/features/inventario-ti/components/BienForm'

export function NuevoEquipoView() {
  const router = useRouter()

  return (
    <PageShell>
      <PageHeader
        title="Registrar equipo"
        description="El código institucional es el que asignó Activos Fijos. La custodia se registra después, desde la ficha."
        backHref={ROUTES.TIC.EQUIPOS}
      />
      <BienForm
        bien={null}
        onGuardado={(bien) => router.push(ROUTES.TIC.EQUIPO(bien.id))}
        onCancelar={() => router.push(ROUTES.TIC.EQUIPOS)}
      />
    </PageShell>
  )
}
