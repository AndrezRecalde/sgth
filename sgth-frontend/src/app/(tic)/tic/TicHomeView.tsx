'use client'

import Link from 'next/link'
import { Button, Stack } from '@mantine/core'
import { IconDevices, IconDroplet, IconLifebuoy, IconPresentation } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell, SectionHeading } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useTableroTic } from '@/features/tablero-tic/hooks/useTableroTic'
import { TableroConsumibles } from '@/features/tablero-tic/components/TableroConsumibles'
import { TableroEquipos } from '@/features/tablero-tic/components/TableroEquipos'
import { TableroPrestamos } from '@/features/tablero-tic/components/TableroPrestamos'
import { TableroTickets } from '@/features/tablero-tic/components/TableroTickets'

function Enlace({ href, children }: { href: string; children: string }) {
  return <Button variant="subtle" size="xs" component={Link} href={href}>{children}</Button>
}

/**
 * Inicio del subsistema Tecnologías (fase 4.1): la mesa de ayuda, el
 * inventario y los préstamos, cada bloque con su permiso. Quien solo consulta
 * el inventario —el auditor— no ve la mesa de ayuda.
 */
export function TicHomeView() {
  const { data, isLoading, error } = useTableroTic()
  const vacio = !!data && !data.tickets && !data.equipos && !data.prestamos

  return (
    <PageShell>
      <PageHeader title="Tecnologías" description="Unidad de Tecnologías de la Información y Comunicación" />
      <DataState loading={isLoading} error={error} empty={vacio}
        emptyProps={{ icon: IconDevices, title: 'Sin nada que mostrar', description: 'Su rol no incluye la mesa de ayuda, el inventario ni los préstamos.' }}>
        <Stack gap="xl">
          {data?.tickets && (
            <Stack gap="md">
              <SectionHeading title="Mesa de ayuda" icon={<IconLifebuoy size={18} />} action={<Enlace href={ROUTES.TIC.TICKETS}>Ir a la bandeja</Enlace>} />
              <TableroTickets datos={data.tickets} />
            </Stack>
          )}
          {data?.equipos && (
            <Stack gap="md">
              <SectionHeading title="Inventario de equipos" icon={<IconDevices size={18} />} action={<Enlace href={ROUTES.TIC.EQUIPOS}>Ver equipos</Enlace>} />
              <TableroEquipos datos={data.equipos} />
            </Stack>
          )}
          {data?.consumibles && (
            <Stack gap="md">
              <SectionHeading title="Consumibles" icon={<IconDroplet size={18} />} action={<Enlace href={ROUTES.TIC.CONSUMIBLES}>Ver el catálogo</Enlace>} />
              <TableroConsumibles datos={data.consumibles} />
            </Stack>
          )}
          {data?.prestamos && (
            <Stack gap="md">
              <SectionHeading title="Préstamo de equipos" icon={<IconPresentation size={18} />} />
              <TableroPrestamos datos={data.prestamos} />
            </Stack>
          )}
        </Stack>
      </DataState>
    </PageShell>
  )
}
