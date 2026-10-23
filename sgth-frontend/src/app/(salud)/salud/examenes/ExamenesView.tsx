'use client'

import { useState } from 'react'
import { Tabs } from '@mantine/core'
import { IconClockHour4, IconFlask, IconListCheck } from '@tabler/icons-react'
import { PageHeader, PageShell } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { BandejaExamenesPanel } from '@/features/dispensario/components/examenes/BandejaExamenesPanel'
import { CatalogoExamenesPanel } from '@/features/dispensario/components/examenes/CatalogoExamenesPanel'
import { PerfilesExamenPanel } from '@/features/dispensario/components/examenes/PerfilesExamenPanel'

/**
 * Los exámenes del Dispensario: la bandeja de lo que espera resultados, que
 * ve también Enfermería para el seguimiento, y lo que se ofrece al pedirlos.
 * Los perfiles los arma cada profesional para su especialidad; el catálogo,
 * solo la administración (P2).
 */
export function ExamenesView() {
  const { hasRole } = useAuth()
  const catalogo = hasRole('admin-dispensario')
  const [pestana, setPestana] = useState<string | null>('pendientes')

  return (
    <PageShell>
      <PageHeader
        title="Exámenes"
        description="Órdenes que esperan resultados, perfiles y catálogo de exámenes"
      />
      <Tabs value={pestana} onChange={setPestana} keepMounted={false}>
        <Tabs.List>
          <Tabs.Tab value="pendientes" leftSection={<IconClockHour4 size={14} />}>Pendientes</Tabs.Tab>
          <Tabs.Tab value="perfiles" leftSection={<IconListCheck size={14} />}>Perfiles</Tabs.Tab>
          {catalogo && <Tabs.Tab value="catalogo" leftSection={<IconFlask size={14} />}>Catálogo</Tabs.Tab>}
        </Tabs.List>
        <Tabs.Panel value="pendientes" pt="lg"><BandejaExamenesPanel /></Tabs.Panel>
        <Tabs.Panel value="perfiles" pt="lg"><PerfilesExamenPanel /></Tabs.Panel>
        {catalogo && <Tabs.Panel value="catalogo" pt="lg"><CatalogoExamenesPanel /></Tabs.Panel>}
      </Tabs>
    </PageShell>
  )
}
