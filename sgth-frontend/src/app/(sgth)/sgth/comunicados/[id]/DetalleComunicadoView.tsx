'use client'

import { Alert, Grid, Skeleton } from '@mantine/core'
import { IconChecklist, IconNews } from '@tabler/icons-react'
import { EmptyState, PageHeader, PageShell, SectionCard, StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { ComunicadoAcciones } from '@/features/comunicados/components/ComunicadoAcciones'
import { ComunicadoContenido } from '@/features/comunicados/components/ComunicadoContenido'
import { ComunicadoResumen } from '@/features/comunicados/components/ComunicadoResumen'
import { TONO_VIGENCIA } from '@/features/comunicados/constants/comunicados'
import { useArchivosComunicado } from '@/features/comunicados/hooks/useArchivosComunicado'
import { useComunicado, usePortadaComunicado } from '@/features/comunicados/hooks/useComunicados'

/**
 * Una publicación (docs/diseno-noticias-comunicados.md, 6.4): a la izquierda,
 * cómo la verá el servidor —con el mismo componente que el Portal—; a la
 * derecha, a quién va, sus fechas y su historial. Desde aquí se publica.
 */
export function DetalleComunicadoView({ id }: { id: string }) {
  const comunicadoId = Number(id)
  const { data: c, isLoading, error } = useComunicado(comunicadoId)
  const { data: portada } = usePortadaComunicado(c)
  const { descargarAdjunto } = useArchivosComunicado(comunicadoId)

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
        <PageHeader title="Publicación" backHref={ROUTES.SGTH.COMUNICADOS} />
        <EmptyState
          icon={IconNews}
          title={error ? 'No se pudo abrir la publicación' : 'Publicación no encontrada'}
          description="No existe o ya no está disponible. Vuelva al listado y ábrala desde allí."
        />
      </PageShell>
    )
  }

  return (
    <PageShell>
      <PageHeader
        title={c.titulo}
        estado={<StatusBadge tone={TONO_VIGENCIA[c.vigencia]}>{c.vigencia_etiqueta}</StatusBadge>}
        backHref={ROUTES.SGTH.COMUNICADOS}
        actions={<ComunicadoAcciones comunicado={c} />}
      />

      <Grid gap="md">
        <Grid.Col span={{ base: 12, lg: 8 }}>
          <SectionCard
            title="Cómo lo verá el servidor"
            description={c.estado === 'borrador' ? 'Vista previa del borrador guardado.' : undefined}
          >
            <ComunicadoContenido
              comunicado={c}
              portadaSrc={portada}
              onDescargar={(adjunto) => descargarAdjunto.mutate(adjunto)}
              pie={c.requiere_confirmacion && c.estado !== 'retirado' && (
                <Alert variant="light" color="amber" icon={<IconChecklist size={16} />}>
                  Al final, cada servidor encontrará el botón «Confirmo que he leído este comunicado».
                </Alert>
              )}
            />
          </SectionCard>
        </Grid.Col>
        <Grid.Col span={{ base: 12, lg: 4 }}>
          <ComunicadoResumen comunicado={c} />
        </Grid.Col>
      </Grid>
    </PageShell>
  )
}
