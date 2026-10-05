'use client'

import { useState } from 'react'
import { Alert, Button, Divider, Group, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconInfoCircle, IconTemplate } from '@tabler/icons-react'
import { DataState, SectionHeading, StatusBadge } from '@/components/ui'
import { useCriterios } from '../hooks/useCriterio'
import type { SeccionCriterio } from '../services/criterioService'
import { AgregarCriterioModal } from './AgregarCriterioModal'
import { SeccionCriterios } from './SeccionCriterios'
import { SeleccionarPlantillaModal } from './SeleccionarPlantillaModal'

interface Props {
  convocatoriaId: number
  editable:       boolean
}

/** La pestaña «Criterios de evaluación»: deben sumar 100 para publicar y para calificar. */
export function TabCriterios({ convocatoriaId, editable }: Props) {
  const { data: criterios = [], isLoading, error, refetch } = useCriterios(convocatoriaId)
  const [modalAbierto, modal] = useDisclosure(false)
  const [seccion, setSeccion] = useState<SeccionCriterio>('meritos')
  const [plantillaAbierta, plantilla] = useDisclosure(false)

  const total = criterios.reduce((s, c) => s + Number(c.puntaje_maximo), 0)
  const agregarEn = (s: SeccionCriterio) => { setSeccion(s); modal.open() }

  return (
    <Stack gap="md" p="md">
      {!editable && (
        <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            Los criterios solo se modifican mientras la convocatoria está en borrador.
          </Text>
        </Alert>
      )}

      <SectionHeading
        title="Criterios configurados"
        action={
          <Group gap="xs">
            <StatusBadge tone={total === 100 ? 'success' : 'warning'} size="md">
              Total: {total.toFixed(0)} / 100 pts
            </StatusBadge>
            {editable && (
              <Button size="compact-xs" variant="light" leftSection={<IconTemplate size={12} />} onClick={plantilla.open}>
                Usar plantilla
              </Button>
            )}
          </Group>
        }
      />

      <DataState loading={isLoading} error={error} errorTitle="No se pudieron cargar los criterios"
        onRetry={refetch} skeletonRows={3}>
        <Stack gap="md">
          {total !== 100 && criterios.length > 0 && (
            <Alert color="amber" variant="light" icon={<IconInfoCircle size={16} />}>
              <Text size="xs">
                Los criterios deben sumar exactamente 100 puntos para publicar y
                calificar. Ahora suman {total.toFixed(0)}.
              </Text>
            </Alert>
          )}

          <SeccionCriterios titulo="Méritos (hoja de vida)" criterios={criterios.filter(c => c.seccion === 'meritos')}
            convocatoriaId={convocatoriaId} editable={editable} onAgregar={() => agregarEn('meritos')} />
          <Divider />
          <SeccionCriterios titulo="Oposición (evaluación directa)" criterios={criterios.filter(c => c.seccion === 'oposicion')}
            convocatoriaId={convocatoriaId} editable={editable} onAgregar={() => agregarEn('oposicion')} />
        </Stack>
      </DataState>

      <AgregarCriterioModal opened={modalAbierto} onClose={modal.close}
        convocatoriaId={convocatoriaId} seccionInicial={seccion} />
      <SeleccionarPlantillaModal opened={plantillaAbierta} onClose={plantilla.close}
        convocatoriaId={convocatoriaId} tieneCriterios={criterios.length > 0} />
    </Stack>
  )
}
