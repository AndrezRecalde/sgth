'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button, Group, Skeleton } from '@mantine/core'
import { IconPresentation } from '@tabler/icons-react'
import { EmptyState, PageHeader, PageShell, SectionCard } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { EstadoPrestamoBadge, PrestamoDetalleCuerpo } from '@/features/prestamos-ti/components/PrestamoDetalle'
import { PrestamoModalesTic, accionesPrestamo, useAprobarConConfirmacion, type AccionPrestamo } from '@/features/prestamos-ti/components/PrestamoModalesTic'
import { useActaPrestamo, usePrestamoTic } from '@/features/prestamos-ti/hooks/usePrestamosTic'

/** El préstamo, con quién pidió y quién hizo cada paso, y el paso que toca. */
export function PrestamoTicView({ id }: { id: number }) {
  const router = useRouter()
  const { hasPermiso } = useAuth()
  const { data: p, isLoading } = usePrestamoTic(id)
  const [accion, setAccion] = useState<AccionPrestamo>(null)
  const aprobar = useAprobarConConfirmacion()
  const acta = useActaPrestamo()

  if (isLoading) {
    return <PageShell><Skeleton height={80} radius="lg" /><Skeleton height={360} radius="lg" /></PageShell>
  }

  if (!p) {
    return (
      <PageShell>
        <PageHeader title="Préstamo" backHref={ROUTES.TIC.PRESTAMOS} />
        <EmptyState icon={IconPresentation} title="Préstamo no encontrado" description="Vuelva a la bandeja y búsquelo por su folio." />
      </PageShell>
    )
  }

  const acciones = accionesPrestamo(p, {
    puedeGestionar: hasPermiso('gestionar-prestamos'),
    abrir: setAccion,
    aprobar,
    acta: (x) => acta.mutate({ id: x.id, folio: x.folio }),
  }).filter((a) => !a.hidden)

  return (
    <PageShell>
      <PageHeader
        title={p.folio}
        description={`${p.servidor?.nombre ?? ''}${p.directo ? ' · préstamo directo' : ''}`}
        estado={<EstadoPrestamoBadge prestamo={p} />}
        onBack={() => router.back()}
        actions={acciones.length > 0 ? (
          <Group gap="xs">
            {acciones.map((a) => (
              <Button key={a.label} variant={a.color === 'red' ? 'subtle' : 'light'} color={a.color} leftSection={a.icon} onClick={a.onClick}>
                {a.label}
              </Button>
            ))}
          </Group>
        ) : undefined}
      />
      <SectionCard title="Detalle">
        <PrestamoDetalleCuerpo prestamo={p} paraTic />
      </SectionCard>
      <PrestamoModalesTic accion={accion} onClose={() => setAccion(null)} />
    </PageShell>
  )
}
