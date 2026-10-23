'use client'

import { useState } from 'react'
import { Tabs } from '@mantine/core'
import { IconFlask, IconListCheck } from '@tabler/icons-react'
import { PageHeader, PageShell } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { CatalogoExamenesPanel } from '@/features/dispensario/components/examenes/CatalogoExamenesPanel'
import { PerfilesExamenPanel } from '@/features/dispensario/components/examenes/PerfilesExamenPanel'

/**
 * Lo que se ofrece al pedir exámenes. Los perfiles los arma cada profesional
 * para su especialidad; el catálogo, solo la administración (P2).
 */
export function ExamenesView() {
  const { hasRole } = useAuth()
  const catalogo = hasRole('admin-dispensario')
  const [pestana, setPestana] = useState<string | null>('perfiles')

  return (
    <PageShell>
      <PageHeader
        title="Exámenes"
        description="Perfiles y catálogo de exámenes que se ofrecen al pedirlos en la consulta"
      />
      <Tabs value={pestana} onChange={setPestana} keepMounted={false}>
        <Tabs.List>
          <Tabs.Tab value="perfiles" leftSection={<IconListCheck size={14} />}>Perfiles</Tabs.Tab>
          {catalogo && <Tabs.Tab value="catalogo" leftSection={<IconFlask size={14} />}>Catálogo</Tabs.Tab>}
        </Tabs.List>
        <Tabs.Panel value="perfiles" pt="lg"><PerfilesExamenPanel /></Tabs.Panel>
        {catalogo && <Tabs.Panel value="catalogo" pt="lg"><CatalogoExamenesPanel /></Tabs.Panel>}
      </Tabs>
    </PageShell>
  )
}
