'use client'

import { useState } from 'react'
import { Stack, Tabs } from '@mantine/core'
import {
  IconBuilding, IconCategory, IconClockHour4, IconFileCertificate, IconLifebuoy, IconPresentation, IconTag, IconUsersGroup,
} from '@tabler/icons-react'
import { PageHeader, PageShell } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { ContratosPanel } from '@/features/inventario-ti/components/ContratosPanel'
import { MarcasPanel, UbicacionesPanel } from '@/features/inventario-ti/components/MarcasUbicacionesPanel'
import { TiposPanel } from '@/features/inventario-ti/components/TiposPanel'
import { UbicacionesPorUnidadPanel } from '@/features/inventario-ti/components/UbicacionesPorUnidadPanel'
import { ReglasPrestamoPanel } from '@/features/prestamos-ti/components/ReglasPrestamoPanel'
import { CategoriasTicketPanel } from '@/features/helpdesk/components/CategoriasTicketPanel'
import { SlaPanel } from '@/features/helpdesk/components/SlaPanel'
import { TecnicosPanel } from '@/features/helpdesk/components/TecnicosPanel'

/**
 * Los catálogos del inventario —un registro que algún equipo usa no se
 * elimina—, las reglas del préstamo de equipos y la configuración de la mesa
 * de ayuda. Cada pestaña se ve con su permiso.
 */
export function ConfiguracionTicView() {
  const { hasPermiso } = useAuth()
  const inventario = hasPermiso('gestionar-inventario-ti')
  const sla = hasPermiso('configurar-sla')
  const tecnicos = hasPermiso('gestionar-tecnicos')
  const [pestana, setPestana] = useState<string | null>(inventario ? 'tipos' : sla ? 'categorias-ticket' : 'tecnicos')

  return (
    <PageShell>
      <PageHeader
        title="Configuración"
        description="Catálogos del inventario, reglas del préstamo y la mesa de ayuda"
      />
      <Tabs value={pestana} onChange={setPestana} keepMounted={false}>
        <Tabs.List>
          {inventario && (
            <>
              <Tabs.Tab value="tipos" leftSection={<IconCategory size={14} />}>Tipos y subtipos</Tabs.Tab>
              <Tabs.Tab value="marcas" leftSection={<IconTag size={14} />}>Marcas</Tabs.Tab>
              <Tabs.Tab value="ubicaciones" leftSection={<IconBuilding size={14} />}>Ubicaciones</Tabs.Tab>
              <Tabs.Tab value="contratos" leftSection={<IconFileCertificate size={14} />}>Contratos de vigencia</Tabs.Tab>
              <Tabs.Tab value="prestamos" leftSection={<IconPresentation size={14} />}>Préstamos</Tabs.Tab>
            </>
          )}
          {sla && (
            <>
              <Tabs.Tab value="categorias-ticket" leftSection={<IconLifebuoy size={14} />}>Categorías de tickets</Tabs.Tab>
              <Tabs.Tab value="sla" leftSection={<IconClockHour4 size={14} />}>SLA</Tabs.Tab>
            </>
          )}
          {tecnicos && <Tabs.Tab value="tecnicos" leftSection={<IconUsersGroup size={14} />}>Técnicos</Tabs.Tab>}
        </Tabs.List>
        {inventario && (
          <>
            <Tabs.Panel value="tipos" pt="lg"><TiposPanel /></Tabs.Panel>
            <Tabs.Panel value="marcas" pt="lg"><MarcasPanel /></Tabs.Panel>
            <Tabs.Panel value="ubicaciones" pt="lg">
              <Stack gap="lg">
                <UbicacionesPanel />
                <UbicacionesPorUnidadPanel puedeEditar={inventario} />
              </Stack>
            </Tabs.Panel>
            <Tabs.Panel value="contratos" pt="lg"><ContratosPanel /></Tabs.Panel>
            <Tabs.Panel value="prestamos" pt="lg"><ReglasPrestamoPanel puedeEditar={hasPermiso('gestionar-prestamos')} /></Tabs.Panel>
          </>
        )}
        {sla && (
          <>
            <Tabs.Panel value="categorias-ticket" pt="lg"><CategoriasTicketPanel /></Tabs.Panel>
            <Tabs.Panel value="sla" pt="lg"><SlaPanel /></Tabs.Panel>
          </>
        )}
        {tecnicos && <Tabs.Panel value="tecnicos" pt="lg"><TecnicosPanel /></Tabs.Panel>}
      </Tabs>
    </PageShell>
  )
}
