'use client'

import { useState } from 'react'
import { Alert, Divider, Stack, Text } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { useRouter } from 'next/navigation'
import { DataState, PageHeader, PageShell, SectionCard, StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { AgregarCriterioPlantillaModal } from '@/features/seleccion/components/AgregarCriterioPlantillaModal'
import { SeccionCriterios } from '@/features/seleccion/components/SeccionCriterios'
import { useEliminarCriterioPlantilla, usePlantillaDetalle } from '@/features/seleccion/hooks/usePlantilla'
import type { SeccionCriterio } from '@/features/seleccion/services/criterioService'
import { TIPO_CONTRATO_PLANTILLA_OPTIONS } from '@/features/seleccion/services/plantillaService'

/**
 * Una plantilla de criterios (2026-10-05). Las secciones son las mismas que
 * en la convocatoria (SeccionCriterios); antes la página tenía su propia
 * copia y pasaba de 250 líneas.
 */
export function DetallePlantillaView({ id }: { id: string }) {
  const plantillaId = Number(id)
  const router = useRouter()
  const [seccion, setSeccion] = useState<SeccionCriterio>('meritos')
  const [modalAbierto, modal] = useDisclosure(false)
  const { data: plantilla, isLoading, error, refetch } = usePlantillaDetalle(plantillaId)
  const eliminar = useEliminarCriterioPlantilla(plantillaId)

  const criterios = plantilla?.criterios ?? []
  const total = criterios.reduce((s, c) => s + Number(c.puntaje_maximo), 0)
  const agregarEn = (s: SeccionCriterio) => { setSeccion(s); modal.open() }
  const seccionProps = { editable: true, onEliminar: (cid: number) => eliminar.mutate(cid) }

  return (
    <PageShell>
      <PageHeader
        title={plantilla?.nombre ?? 'Plantilla'}
        description={plantilla
          ? TIPO_CONTRATO_PLANTILLA_OPTIONS.find(o => o.value === plantilla.tipo_contrato)?.label ?? 'General'
          : undefined}
        onBack={() => router.push(ROUTES.SGTH.PLANTILLAS)}
      />

      <DataState loading={isLoading} error={error} errorTitle="No se pudo cargar la plantilla"
        errorHint="Si el enlace es antiguo, vuelva al listado y ábrala desde allí." onRetry={refetch} skeletonRows={4}>
        {plantilla && (
          <SectionCard
            title="Criterios"
            description={plantilla.descripcion ?? undefined}
            actions={
              <StatusBadge tone={total === 100 ? 'success' : 'warning'} size="md">
                Total: {total.toFixed(0)} / 100 pts
              </StatusBadge>
            }
          >
            <Stack gap="md">
              {total !== 100 && criterios.length > 0 && (
                <Alert color="amber" variant="light" icon={<IconInfoCircle size={16} />}>
                  <Text size="xs">
                    Los criterios deben sumar exactamente 100 puntos: una
                    convocatoria con esta plantilla no se podrá publicar hasta
                    corregirlos. Ahora suman {total.toFixed(0)}.
                  </Text>
                </Alert>
              )}
              <SeccionCriterios titulo="Méritos" criterios={criterios.filter(c => c.seccion === 'meritos')}
                onAgregar={() => agregarEn('meritos')} {...seccionProps} />
              <Divider />
              <SeccionCriterios titulo="Oposición" criterios={criterios.filter(c => c.seccion === 'oposicion')}
                onAgregar={() => agregarEn('oposicion')} {...seccionProps} />
            </Stack>
          </SectionCard>
        )}
      </DataState>

      <AgregarCriterioPlantillaModal opened={modalAbierto} onClose={modal.close}
        plantillaId={plantillaId} seccionInicial={seccion} />
    </PageShell>
  )
}
