'use client'

import { useState } from 'react'
import { Button, Group, SegmentedControl, Center, Switch } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconCubePlus,
  IconListTree,
  IconHierarchy,
  IconFileTypePdf,
} from '@tabler/icons-react'
import { PageHeader, PageShell } from '@/components/ui'
import { OrganigramaTree } from '@/features/estructura/components/OrganigramaTree'
import { OrganigramaChart } from '@/features/estructura/components/OrganigramaChart'
import { UnidadDrawer } from '@/features/estructura/components/UnidadDrawer'
import { UnidadModal } from '@/features/estructura/components/UnidadModal'
import { useOrganigrama } from '@/features/estructura/hooks/useOrganigrama'
import { useGestionOrganigrama } from '@/features/estructura/hooks/useGestionOrganigrama'
import { useUnidad } from '@/features/estructura/hooks/useUnidad'
import { estructuraService } from '@/features/estructura/services/estructuraService'
import type { UnidadConRelaciones } from '@/types/api'

export function OrganigramaView() {
  const [vista, setVista] = useState<'acordeon' | 'nodo'>('acordeon')
  const [subprocesos, setSubprocesos] = useState(true)

  const [drawerOpened, drawerHandlers] = useDisclosure(false)
  const [unidadSelId, setUnidadSelId] = useState<number | null>(null)

  const { data: organigrama, isLoading, error } = useOrganigrama()
  const { data: unidadDetalle, isLoading: isLoadingDrawer } = useUnidad(unidadSelId)
  const gestion = useGestionOrganigrama()

  const abrirUnidad = (unidad: UnidadConRelaciones) => {
    // La institución no tiene detalle propio que mostrar; las unidades y sus
    // subprocesos sí.
    if ((unidad.nivel ?? 1) <= 1) return
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
          <Group gap="xs">
            <Button
              variant="default"
              leftSection={<IconFileTypePdf size={16} />}
              component="a"
              href={estructuraService.organigramaPdfUrl()}
              target="_blank"
              rel="noopener"
            >
              Exportar PDF
            </Button>
            {gestion.puedeGestionar && (
              <Button
                color="emerald"
                leftSection={<IconCubePlus size={16} />}
                variant="light"
                onClick={gestion.crear}
              >
                Nueva unidad
              </Button>
            )}
          </Group>
        }
      />

      <Group justify="space-between">
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

        {vista === 'nodo' && (
          <Switch
            label="Mostrar subprocesos"
            color="emerald"
            checked={subprocesos}
            onChange={(e) => setSubprocesos(e.currentTarget.checked)}
          />
        )}
      </Group>

      {vista === 'acordeon' ? (
        <OrganigramaTree
          unidades={organigrama ?? []}
          isLoading={isLoading}
          error={error}
          onEditar={gestion.editar}
          onAgregarHija={gestion.agregarHija}
          onEliminar={gestion.eliminarUnidad}
        />
      ) : (
        <OrganigramaChart
          unidades={organigrama ?? []}
          isLoading={isLoading}
          error={error}
          onNodeClick={abrirUnidad}
          mostrarSubprocesos={subprocesos}
        />
      )}

      <UnidadModal
        opened={gestion.modalAbierto}
        onClose={gestion.cerrar}
        unidad={gestion.enEdicion}
        padre={gestion.padre}
      />
      <UnidadDrawer
        opened={drawerOpened}
        onClose={cerrarDrawer}
        unidad={unidadDetalle ?? null}
        isLoading={isLoadingDrawer}
      />
    </PageShell>
  )
}
