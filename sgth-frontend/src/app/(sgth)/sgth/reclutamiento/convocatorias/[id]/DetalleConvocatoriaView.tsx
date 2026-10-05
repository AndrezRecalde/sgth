'use client'

import { Card, Stack, Tabs } from '@mantine/core'
import { IconChartBar, IconClipboardList, IconUsers } from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { DataState, PageHeader, PageShell } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { useConvocatoriaDetalle, usePostulantes } from '@/features/seleccion/hooks/useConvocatoria'
import { ConvocatoriaAcciones } from '@/features/seleccion/components/ConvocatoriaAcciones'
import { ConvocatoriaResumen } from '@/features/seleccion/components/ConvocatoriaResumen'
import { TabCandidatos } from '@/features/seleccion/components/TabCandidatos'
import { TabCriterios } from '@/features/seleccion/components/TabCriterios'
import { TabRanking } from '@/features/seleccion/components/TabRanking'

/**
 * El detalle de una convocatoria formal. Pasó de 456 líneas a esta página y
 * sus piezas (2026-10-05): acciones, resumen y una pestaña por fase.
 */
export function DetalleConvocatoriaView({ id }: { id: string }) {
  const convocatoriaId = Number(id)
  const router = useRouter()
  const { data: convocatoria, isLoading, error, refetch } = useConvocatoriaDetalle(convocatoriaId)
  const { data: postulantes = [] } = usePostulantes(convocatoriaId)

  // El analista ve y califica; gestionar es de admin-uath (2026-10-05).
  const { hasPermiso } = useAuth()
  const gestiona = hasPermiso('gestionar-convocatorias')
  const califica = hasPermiso('evaluar-postulantes')

  return (
    <PageShell>
      <PageHeader
        title={convocatoria?.titulo ?? 'Convocatoria'}
        description={convocatoria?.codigo}
        onBack={() => router.push(ROUTES.SGTH.CONVOCATORIAS)}
        actions={convocatoria && gestiona && <ConvocatoriaAcciones convocatoria={convocatoria} />}
      />

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudo cargar la convocatoria"
        errorHint="Si el enlace es antiguo, vuelva al listado y ábrala desde allí."
        onRetry={refetch}
        skeletonRows={4}
      >
        {convocatoria && (
          <Stack gap="md">
            <ConvocatoriaResumen convocatoria={convocatoria} />

            <Tabs defaultValue="candidatos" radius="lg">
              <Tabs.List>
                <Tabs.Tab value="candidatos" leftSection={<IconUsers size={14} />}>
                  Candidatos ({postulantes.length})
                </Tabs.Tab>
                <Tabs.Tab value="criterios" leftSection={<IconClipboardList size={14} />}>
                  Criterios de evaluación
                </Tabs.Tab>
                <Tabs.Tab value="ranking" leftSection={<IconChartBar size={14} />}>
                  Ranking
                </Tabs.Tab>
              </Tabs.List>

              <Tabs.Panel value="candidatos" pt="sm">
                <TabCandidatos
                  convocatoriaId={convocatoriaId}
                  estadoConvocatoria={convocatoria.estado}
                  gestiona={gestiona}
                  califica={califica}
                />
              </Tabs.Panel>

              <Tabs.Panel value="criterios" pt="sm">
                <Card withBorder radius="lg">
                  <TabCriterios convocatoriaId={convocatoriaId} editable={gestiona && convocatoria.estado === 'borrador'} />
                </Card>
              </Tabs.Panel>

              <Tabs.Panel value="ranking" pt="sm">
                <Card withBorder radius="lg">
                  <TabRanking
                    convocatoriaId={convocatoriaId}
                    estadoConvocatoria={convocatoria.estado}
                    vacantes={convocatoria.vacantes}
                    puedeGestionar={gestiona}
                  />
                </Card>
              </Tabs.Panel>
            </Tabs>
          </Stack>
        )}
      </DataState>
    </PageShell>
  )
}
