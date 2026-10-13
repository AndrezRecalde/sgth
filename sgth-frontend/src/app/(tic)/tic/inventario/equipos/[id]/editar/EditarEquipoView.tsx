'use client'

import { useRouter } from 'next/navigation'
import { Skeleton } from '@mantine/core'
import { IconDevices } from '@tabler/icons-react'
import { EmptyState, PageHeader, PageShell } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { BienForm } from '@/features/inventario-ti/components/BienForm'
import { useBien } from '@/features/inventario-ti/hooks/useBienes'

export function EditarEquipoView({ id }: { id: number }) {
  const router = useRouter()
  const { data: bien, isLoading } = useBien(id)

  if (isLoading) {
    return <PageShell><Skeleton height={80} radius="lg" /><Skeleton height={420} radius="lg" /></PageShell>
  }

  if (!bien) {
    return (
      <PageShell>
        <PageHeader title="Editar equipo" backHref={ROUTES.TIC.EQUIPOS} />
        <EmptyState
          icon={IconDevices}
          title="Equipo no encontrado"
          description="No existe o se retiró del inventario. Vuelva al listado y búsquelo allí."
        />
      </PageShell>
    )
  }

  return (
    <PageShell>
      <PageHeader
        title={`Editar ${bien.codigo_institucional}`}
        description={[bien.tipo?.nombre, bien.marca?.nombre, bien.modelo].filter(Boolean).join(' · ')}
        backHref={ROUTES.TIC.EQUIPO(bien.id)}
      />
      {/* La clave fuerza un formulario nuevo si se pasa de un equipo a otro. */}
      <BienForm
        key={bien.id}
        bien={bien}
        onGuardado={() => router.push(ROUTES.TIC.EQUIPO(bien.id))}
        onCancelar={() => router.push(ROUTES.TIC.EQUIPO(bien.id))}
      />
    </PageShell>
  )
}
