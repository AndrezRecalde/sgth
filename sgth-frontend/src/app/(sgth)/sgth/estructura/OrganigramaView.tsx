'use client'

import { useState } from 'react'
import { Button, Group, SegmentedControl, Center } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconCubePlus,
  IconListTree,
  IconHierarchy,
} from '@tabler/icons-react'
import { PageHeader, PageShell } from '@/components/ui'
import { OrganigramaTree } from '@/features/estructura/components/OrganigramaTree'
import { OrganigramaChart } from '@/features/estructura/components/OrganigramaChart'
import { UnidadDrawer } from '@/features/estructura/components/UnidadDrawer'
import { UnidadModal } from '@/features/estructura/components/UnidadModal'
import { useOrganigrama } from '@/features/estructura/hooks/useOrganigrama'
import { useUnidad } from '@/features/estructura/hooks/useUnidad'
import type { UnidadConRelaciones } from '@/types/api'

export function OrganigramaView() {
  const [vista, setVista] = useState<'acordeon' | 'nodo'>('acordeon')

  const [unidadModal, unidadModalHandlers] = useDisclosure(false)
  const [drawerOpened, drawerHandlers] = useDisclosure(false)
  const [unidadSelId, setUnidadSelId] = useState<number | null>(null)

  const { data: organigrama, isLoading, error } = useOrganigrama()
  const { data: unidadDetalle, isLoading: isLoadingDrawer } = useUnidad(unidadSelId)

  const abrirUnidad = (unidad: UnidadConRelaciones) => {
    // Solo el tercer nivel tiene detalle propio que mostrar.
    if ((unidad.nivel ?? 0) !== 2) return
    setUnidadSelId(Number(unidad.id))
    drawerHandlers.open()
  }

  const cerrarDrawer = () => {
    drawerHandlers.close()
    setUnidadSelId(null)
  }

  return (
    // `fluid`: el organigrama es un lienzo, no un listado. Con el ancho de
    // lectura los nodos de los niveles profundos quedan comprimidos.
    <PageShell fluid>
      <PageHeader
        title="Organigrama"
        description="Estructura orgánica del GAD Provincial de Esmeraldas"
        actions={
          <Button
            color="emerald"
            leftSection={<IconCubePlus size={16} />}
            variant="light"
            onClick={unidadModalHandlers.open}
          >
            Nueva unidad
          </Button>
        }
      />

      <Group justify="flex-start">
        <SegmentedControl
          value={vista}
          onChange={(v) => setVista(v as 'acordeon' | 'nodo')}
          color="emerald"
          data={[
            {
              value: 'acordeon',
              label: (
                <Center style={{ gap: 10 }}>
                  <IconListTree size={14} />
                  Acordeón
                </Center>
              ),
            },
            {
              value: 'nodo',
              label: (
                <Center style={{ gap: 10 }}>
                  <IconHierarchy size={14} />
                  Nodos
                </Center>
              ),
            },
          ]}
        />
      </Group>

      {vista === 'acordeon' ? (
        <OrganigramaTree
          unidades={organigrama ?? []}
          isLoading={isLoading}
          error={error}
        />
      ) : (
        <OrganigramaChart
          unidades={organigrama ?? []}
          isLoading={isLoading}
          error={error}
          onNodeClick={abrirUnidad}
        />
      )}

      <UnidadModal
        opened={unidadModal}
        onClose={unidadModalHandlers.close}
        unidad={null}
      />
      <UnidadDrawer
        opened={drawerOpened}
        onClose={cerrarDrawer}
        unidad={(unidadDetalle as UnidadConRelaciones) ?? null}
        isLoading={isLoadingDrawer}
      />
    </PageShell>
  )
}
