'use client'

import { useState } from 'react'
import { Tabs } from '@mantine/core'
import { IconBuilding, IconCategory, IconFileCertificate, IconPresentation, IconTag } from '@tabler/icons-react'
import { PageHeader, PageShell } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { ContratosPanel } from '@/features/inventario-ti/components/ContratosPanel'
import { MarcasPanel, UbicacionesPanel } from '@/features/inventario-ti/components/MarcasUbicacionesPanel'
import { TiposPanel } from '@/features/inventario-ti/components/TiposPanel'
import { ReglasPrestamoPanel } from '@/features/prestamos-ti/components/ReglasPrestamoPanel'

/**
 * Los catálogos del inventario —un registro que algún equipo usa no se
 * elimina— y las reglas del préstamo de equipos.
 */
export function ConfiguracionTicView() {
  const [pestana, setPestana] = useState<string | null>('tipos')
  const { hasPermiso } = useAuth()

  return (
    <PageShell>
      <PageHeader
        title="Configuración"
        description="Catálogos del inventario de equipos y reglas del préstamo"
      />
      <Tabs value={pestana} onChange={setPestana} keepMounted={false}>
        <Tabs.List>
          <Tabs.Tab value="tipos" leftSection={<IconCategory size={14} />}>Tipos y subtipos</Tabs.Tab>
          <Tabs.Tab value="marcas" leftSection={<IconTag size={14} />}>Marcas</Tabs.Tab>
          <Tabs.Tab value="ubicaciones" leftSection={<IconBuilding size={14} />}>Ubicaciones</Tabs.Tab>
          <Tabs.Tab value="contratos" leftSection={<IconFileCertificate size={14} />}>Contratos de vigencia</Tabs.Tab>
          <Tabs.Tab value="prestamos" leftSection={<IconPresentation size={14} />}>Préstamos</Tabs.Tab>
        </Tabs.List>
        <Tabs.Panel value="tipos" pt="lg"><TiposPanel /></Tabs.Panel>
        <Tabs.Panel value="marcas" pt="lg"><MarcasPanel /></Tabs.Panel>
        <Tabs.Panel value="ubicaciones" pt="lg"><UbicacionesPanel /></Tabs.Panel>
        <Tabs.Panel value="contratos" pt="lg"><ContratosPanel /></Tabs.Panel>
        <Tabs.Panel value="prestamos" pt="lg"><ReglasPrestamoPanel puedeEditar={hasPermiso('gestionar-prestamos')} /></Tabs.Panel>
      </Tabs>
    </PageShell>
  )
}
