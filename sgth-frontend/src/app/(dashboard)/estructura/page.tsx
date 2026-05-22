'use client'

import { useState } from 'react'
import { Tabs, Box, Button, Group } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconSitemap,
  IconPhone,
  IconBriefcase,
  IconPlus,
} from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { OrganigramaTree } from '@/features/estructura/components/OrganigramaTree'
import { DirectorioTable } from '@/features/estructura/components/DirectorioTable'
import { DirectorioToolbar } from '@/features/estructura/components/DirectorioToolbar'
import { PuestosTab } from '@/features/estructura/components/PuestosTab'
import { UnidadModal } from '@/features/estructura/components/UnidadModal'
import { ExtensionModal } from '@/features/estructura/components/ExtensionModal'
import { useOrganigrama } from '@/features/estructura/hooks/useOrganigrama'
import { useDirectorio } from '@/features/estructura/hooks/useDirectorio'
import { useExtensionMutations } from '@/features/estructura/hooks/useExtensionMutations'
import type { UnidadConRelaciones, ExtensionConRelaciones } from '@/types/api'

export default function EstructuraPage() {
  const [search, setSearch]   = useState('')
  const [unidadId, setUnidadId] = useState<string | null>(null)

  const [unidadModal, unidadModalHandlers]     = useDisclosure(false)
  const [extensionModal, extensionModalHandlers] = useDisclosure(false)

  const [editUnidad, setEditUnidad] =
    useState<UnidadConRelaciones | null>(null)
  const [editExtension, setEditExtension] =
    useState<ExtensionConRelaciones | null>(null)

  const { eliminar: eliminarExtension } = useExtensionMutations()

  const { data: organigrama, isLoading: isLoadingOrg, error: errorOrg } =
    useOrganigrama()

  const { data: directorio = [], isLoading: isLoadingDir } = useDirectorio({
    search: search || undefined,
    unidad_administrativa_id: unidadId ? Number(unidadId) : undefined,
  })

  const handleEditExtension = (ext: ExtensionConRelaciones) => {
    setEditExtension(ext)
    extensionModalHandlers.open()
  }

  const handleCloseExtension = () => {
    setEditExtension(null)
    extensionModalHandlers.close()
  }

  const handleCloseUnidad = () => {
    setEditUnidad(null)
    unidadModalHandlers.close()
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
        </Tabs.List>

        <Tabs.Panel value="organigrama">
          <Group justify="flex-end" mb="md">
            <Button
              leftSection={<IconPlus size={16} />}
              color="emerald"
              onClick={unidadModalHandlers.open}
            >
              Nueva unidad
            </Button>
          </Group>
          <OrganigramaTree
            unidades={organigrama ?? []}
            isLoading={isLoadingOrg}
            error={errorOrg}
          />
        </Tabs.Panel>

        <Tabs.Panel value="directorio">
          <Group justify="flex-end" mb="sm">
            <Button
              leftSection={<IconPlus size={16} />}
              color="emerald"
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
      </Tabs>

      <UnidadModal
        opened={unidadModal}
        onClose={handleCloseUnidad}
        unidad={editUnidad}
      />
      <ExtensionModal
        opened={extensionModal}
        onClose={handleCloseExtension}
        extension={editExtension}
      />
    </Box>
  )
}
