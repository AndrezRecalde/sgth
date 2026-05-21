'use client'

import { useState } from 'react'
import { Tabs, Box, Skeleton } from '@mantine/core'
import { IconSitemap, IconPhone } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { OrganigramaTree } from '@/features/estructura/components/OrganigramaTree'
import { DirectorioTable } from '@/features/estructura/components/DirectorioTable'
import { DirectorioToolbar } from '@/features/estructura/components/DirectorioToolbar'
import { useOrganigrama } from '@/features/estructura/hooks/useOrganigrama'
import { useDirectorio } from '@/features/estructura/hooks/useDirectorio'

export default function EstructuraPage() {
  const { data: organigrama, isLoading: isLoadingOrg, error: errorOrg } = useOrganigrama()
  
  const [search, setSearch] = useState('')
  const [unidadId, setUnidadId] = useState<string | null>(null)
  
  const { data: directorio = [], isLoading: isLoadingDir } = useDirectorio({
    search: search || undefined,
    unidad_administrativa_id: unidadId ? Number(unidadId) : undefined
  })

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
        </Tabs.List>

        <Tabs.Panel value="organigrama">
          {isLoadingOrg ? (
            <Skeleton height={200} radius="md" />
          ) : (
            <OrganigramaTree 
              unidades={organigrama || []} 
              error={errorOrg} 
            />
          )}
        </Tabs.Panel>

        <Tabs.Panel value="directorio">
          <DirectorioToolbar 
            onSearch={setSearch}
            onUnidadChange={setUnidadId}
            onClear={() => {
              setSearch('')
              setUnidadId(null)
            }}
          />
          <DirectorioTable data={directorio} isLoading={isLoadingDir} />
        </Tabs.Panel>
      </Tabs>
    </Box>
  )
}
