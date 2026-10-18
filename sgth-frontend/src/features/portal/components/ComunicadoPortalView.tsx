'use client'

import { Card, Skeleton } from '@mantine/core'
import { IconNews } from '@tabler/icons-react'
import { EmptyState, PageHeader, PageShell } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { ComunicadoContenido } from '@/features/comunicados/components/ComunicadoContenido'
import { useComunicadoPortal, useDescargarAdjuntoPortal } from '@/features/comunicados/hooks/useMisComunicados'
import { ConfirmacionLectura } from './ConfirmacionLectura'

/**
 * Una noticia o un comunicado en el Portal (docs/diseno-noticias-comunicados.md,
 * 5.3): el mismo contenido que Talento Humano revisó en la vista previa y, al
 * final, la confirmación si la pide. Abrirla la deja leída.
 */
export function ComunicadoPortalView({ id }: { id: string }) {
  const comunicadoId = Number(id)
  const { data: c, isLoading } = useComunicadoPortal(comunicadoId)
  const descargar = useDescargarAdjuntoPortal(comunicadoId)

  if (isLoading) {
    return (
      <PageShell>
        <Skeleton height={60} radius="lg" />
        <Skeleton height={420} radius="lg" />
      </PageShell>
    )
  }

  if (!c) {
    return (
      <PageShell>
        <PageHeader title="Noticias y comunicados" backHref={ROUTES.PORTAL.COMUNICADOS} />
        <EmptyState
          icon={IconNews}
          title="Publicación no disponible"
          description="No existe, ya no está publicada o no está dirigida a usted. Vuelva al listado y ábrala desde allí."
        />
      </PageShell>
    )
  }

  return (
    <PageShell>
      <PageHeader title="Noticias y comunicados" backHref={ROUTES.PORTAL.COMUNICADOS} />
      <Card withBorder radius="xl" p={{ base: 'md', sm: 'xl' }}>
        <ComunicadoContenido
          comunicado={c}
          onDescargar={(adjunto) => descargar.mutate(adjunto)}
          pie={<ConfirmacionLectura c={c} />}
        />
      </Card>
    </PageShell>
  )
}
