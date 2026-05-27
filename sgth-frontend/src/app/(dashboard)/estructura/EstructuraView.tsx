'use client'

import { useState } from 'react'
import {
  Tabs, Box, Button, Group, SegmentedControl,
  Center,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconSitemap, IconPhone, IconBriefcase,
  IconCubePlus, IconListTree, IconHierarchy,
} from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { OrganigramaTree } from '@/features/estructura/components/OrganigramaTree'
import { OrganigramaChart } from '@/features/estructura/components/OrganigramaChart'
import { UnidadDrawer } from '@/features/estructura/components/UnidadDrawer'
import { DirectorioTable } from '@/features/estructura/components/DirectorioTable'
import { DirectorioToolbar } from '@/features/estructura/components/DirectorioToolbar'
import { PuestosTab } from '@/features/estructura/components/PuestosTab'
import { UnidadModal } from '@/features/estructura/components/UnidadModal'
import { ExtensionModal } from '@/features/estructura/components/ExtensionModal'
import { useOrganigrama } from '@/features/estructura/hooks/useOrganigrama'
import { useUnidad } from '@/features/estructura/hooks/useUnidad'
import { useDirectorio } from '@/features/estructura/hooks/useDirectorio'
import { useExtensionMutations } from '@/features/estructura/hooks/useExtensionMutations'
import type { UnidadConRelaciones, ExtensionConRelaciones } from '@/types/api'
import { CargosTab } from '@/features/estructura/components/CargosTab'
import { GruposOcupacionalesTab } from '@/features/estructura/components/GruposOcupacionalesTab'
import { IconId, IconScale } from '@tabler/icons-react'

export function EstructuraView() {
  const [search, setSearch]   = useState('')
  const [unidadId, setUnidadId] = useState<string | null>(null)
  const [vistaOrg, setVistaOrg] = useState<'acordeon' | 'nodo'>('acordeon')

  const [unidadModal, unidadModalHandlers]       = useDisclosure(false)
  const [extensionModal, extensionModalHandlers] = useDisclosure(false)
  const [drawerOpened, drawerHandlers]           = useDisclosure(false)

  const [editUnidad, setEditUnidad]       = useState<UnidadConRelaciones | null>(null)
  const [editExtension, setEditExtension] = useState<ExtensionConRelaciones | null>(null)
  const [selectedUnidadId, setSelectedUnidadId] = useState<number | null>(null)

  const { eliminar: eliminarExtension } = useExtensionMutations()

  const { data: organigrama, isLoading: isLoadingOrg, error: errorOrg } =
    useOrganigrama()

  const { data: unidadDetalle, isLoading: isLoadingDrawer } =
    useUnidad(selectedUnidadId)

  const { data: directorio = [], isLoading: isLoadingDir } = useDirectorio({
    search: search || undefined,
    unidad_administrativa_id: unidadId ? Number(unidadId) : undefined,
  })

  const handleNodeClick = (unidad: UnidadConRelaciones) => {
    const nivel = unidad.nivel ?? 0
    if (nivel === 2) {
      setSelectedUnidadId(Number(unidad.id))
      drawerHandlers.open()
    }
  }

  const handleCloseDrawer = () => {
    drawerHandlers.close()
    setSelectedUnidadId(null)
  }

  const handleEditExtension = (ext: ExtensionConRelaciones) => {
    setEditExtension(ext)
    extensionModalHandlers.open()
  }

  return (
    <Box>
      <PageHeader
        title="Estructura Organizacional"
        subtitle="GAD Provincial de Esmeraldas"
        icon={<IconSitemap size={28} />}
      />

      <Tabs defaultValue="organigrama" color="emerald">
        <Tabs.List mb="md">
          <Tabs.Tab value="organigrama" leftSection={<IconSitemap size={16} />}>
            Organigrama
          </Tabs.Tab>
          <Tabs.Tab value="directorio" leftSection={<IconPhone size={16} />}>
            Directorio telefónico
          </Tabs.Tab>
          <Tabs.Tab value="puestos" leftSection={<IconBriefcase size={16} />}>
            Puestos
          </Tabs.Tab>
          <Tabs.Tab value="cargos" leftSection={<IconId size={16} />}>
            Cargos
          </Tabs.Tab>
          <Tabs.Tab value="grupos" leftSection={<IconScale size={16} />}>
            Grupos Ocupacionales
          </Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="organigrama">
          <Group justify="space-between" mb="md">
            <SegmentedControl
              value={vistaOrg}
              onChange={(v) => setVistaOrg(v as 'acordeon' | 'nodo')}
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
            <Button
              color="emerald"
              leftSection={<IconCubePlus size={16} />}
              variant="light"
              onClick={unidadModalHandlers.open}
            >
              Nueva unidad
            </Button>
          </Group>

          {vistaOrg === 'acordeon' ? (
            <OrganigramaTree
              unidades={organigrama ?? []}
              isLoading={isLoadingOrg}
              error={errorOrg}
            />
          ) : (
            <OrganigramaChart
              unidades={organigrama ?? []}
              isLoading={isLoadingOrg}
              error={errorOrg}
              onNodeClick={handleNodeClick}
            />
          )}
        </Tabs.Panel>

        <Tabs.Panel value="directorio">
          <Group justify="flex-end" mb="sm">
            <Button
              color="emerald"
              leftSection={<IconCubePlus size={16} />}
              variant="light"
              onClick={extensionModalHandlers.open}
            >
              Nueva extensión
            </Button>
          </Group>
          <DirectorioToolbar
            onSearch={setSearch}
            onUnidadChange={setUnidadId}
            onClear={() => { setSearch(''); setUnidadId(null) }}
          />
          <DirectorioTable
            data={directorio}
            isLoading={isLoadingDir}
            onEdit={handleEditExtension}
            onDelete={(ext) => {
              if (confirm(`¿Eliminar la extensión ${ext.numero_extension ?? ''}?`)) {
                eliminarExtension.mutate(ext.id)
              }
            }}
          />
        </Tabs.Panel>

        <Tabs.Panel value="puestos">
          <PuestosTab />
        </Tabs.Panel>

        <Tabs.Panel value="cargos">
          <CargosTab />
        </Tabs.Panel>

        <Tabs.Panel value="grupos">
          <GruposOcupacionalesTab />
        </Tabs.Panel>
      </Tabs>

      <UnidadModal
        opened={unidadModal}
        onClose={() => { setEditUnidad(null); unidadModalHandlers.close() }}
        unidad={editUnidad}
      />
      <ExtensionModal
        opened={extensionModal}
        onClose={() => { setEditExtension(null); extensionModalHandlers.close() }}
        extension={editExtension}
      />
      <UnidadDrawer
        opened={drawerOpened}
        onClose={handleCloseDrawer}
        unidad={(unidadDetalle as unknown as UnidadConRelaciones) ?? null}
        isLoading={isLoadingDrawer}
      />
    </Box>
  )
}
