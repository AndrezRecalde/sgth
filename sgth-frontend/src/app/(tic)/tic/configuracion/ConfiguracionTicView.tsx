'use client'

import { useState } from 'react'
import { Tabs } from '@mantine/core'
import { IconBuilding, IconCategory, IconFileCertificate, IconTag } from '@tabler/icons-react'
import { PageHeader, PageShell } from '@/components/ui'
import { ContratosPanel } from '@/features/inventario-ti/components/ContratosPanel'
import { MarcasPanel, UbicacionesPanel } from '@/features/inventario-ti/components/MarcasUbicacionesPanel'
import { TiposPanel } from '@/features/inventario-ti/components/TiposPanel'

/** Los catálogos del inventario. Un registro que algún equipo usa no se elimina. */
export function ConfiguracionTicView() {
  const [pestana, setPestana] = useState<string | null>('tipos')

  return (
    <PageShell>
      <PageHeader
        title="Configuración"
        description="Catálogos del inventario de equipos: lo que se elige al registrar un equipo"
      />
      <Tabs value={pestana} onChange={setPestana} keepMounted={false}>
        <Tabs.List>
          <Tabs.Tab value="tipos" leftSection={<IconCategory size={14} />}>Tipos y subtipos</Tabs.Tab>
          <Tabs.Tab value="marcas" leftSection={<IconTag size={14} />}>Marcas</Tabs.Tab>
          <Tabs.Tab value="ubicaciones" leftSection={<IconBuilding size={14} />}>Ubicaciones</Tabs.Tab>
          <Tabs.Tab value="contratos" leftSection={<IconFileCertificate size={14} />}>Contratos de vigencia</Tabs.Tab>
        </Tabs.List>
        <Tabs.Panel value="tipos" pt="lg"><TiposPanel /></Tabs.Panel>
        <Tabs.Panel value="marcas" pt="lg"><MarcasPanel /></Tabs.Panel>
        <Tabs.Panel value="ubicaciones" pt="lg"><UbicacionesPanel /></Tabs.Panel>
        <Tabs.Panel value="contratos" pt="lg"><ContratosPanel /></Tabs.Panel>
      </Tabs>
    </PageShell>
  )
}
