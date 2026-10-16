'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button, Group } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconDroplet, IconPackageImport, IconPlus, IconTruckDelivery } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell, SgthTable } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { ConsumibleModal } from '@/features/consumibles-ti/components/ConsumibleModal'
import { ConsumiblesFiltros } from '@/features/consumibles-ti/components/ConsumiblesFiltros'
import { columnasConsumibles } from '@/features/consumibles-ti/components/consumibles.columns'
import { IngresoModal } from '@/features/consumibles-ti/components/IngresoModal'
import { useConsumibles } from '@/features/consumibles-ti/hooks/useConsumibles'
import type { FiltrosConsumibles } from '@/features/consumibles-ti/services/consumiblesService'

const RETARDO_BUSQUEDA_MS = 300

/**
 * El catálogo de consumibles (fase 5.4): cada uno con su saldo si lo guarda
 * TIC, resaltado si bajó del mínimo, y los modelos de equipo a los que sirve.
 * Desde aquí se registra un ingreso, se entrega o se agrega uno al catálogo.
 */
export function ConsumiblesView() {
  const router = useRouter()
  const { hasPermiso } = useAuth()
  const gestiona = hasPermiso('gestionar-inventario-ti')
  const [filtros, setFiltros] = useState<FiltrosConsumibles>({})
  const [busqueda, setBusqueda] = useState('')
  const [search] = useDebouncedValue(busqueda.trim(), RETARDO_BUSQUEDA_MS)
  const [nuevo, setNuevo] = useState(false)
  const [ingreso, setIngreso] = useState(false)

  const { data = [], isLoading, error } = useConsumibles({ ...filtros, ...(search.length >= 2 ? { search } : {}) })
  const hayFiltros = busqueda.trim() !== '' || Object.values(filtros).some((v) => v !== undefined)

  const acciones = gestiona ? (
    <Group gap="xs">
      <Button variant="subtle" leftSection={<IconPlus size={16} />} onClick={() => setNuevo(true)}>Nuevo consumible</Button>
      <Button variant="subtle" leftSection={<IconPackageImport size={16} />} onClick={() => setIngreso(true)}>Registrar ingreso</Button>
      <Button variant="light" leftSection={<IconTruckDelivery size={16} />} onClick={() => router.push(ROUTES.TIC.CONSUMIBLES_ENTREGAR())}>
        Entregar
      </Button>
    </Group>
  ) : undefined

  return (
    <PageShell>
      <PageHeader
        title="Consumibles"
        description="Tóner, tinta y repuestos: el saldo de lo que guarda TIC y las entregas a cada equipo"
        actions={acciones}
      />
      <ConsumiblesFiltros
        filtros={filtros}
        busqueda={busqueda}
        onBusqueda={setBusqueda}
        onCambiar={(cambio) => setFiltros((f) => ({ ...f, ...cambio }))}
        onLimpiar={() => { setFiltros({}); setBusqueda('') }}
        hayFiltros={hayFiltros}
      />
      <DataState loading={isLoading} error={error} empty={!data.length}
        emptyProps={hayFiltros
          ? { icon: IconDroplet, title: 'Ningún consumible con estos filtros', description: 'Quite algún filtro para ver más.' }
          : {
              icon: IconDroplet,
              title: 'Todavía no hay consumibles',
              description: 'Registre el tóner, la tinta y los repuestos con los modelos de equipo a los que sirven.',
              action: gestiona ? <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setNuevo(true)}>Nuevo consumible</Button> : undefined,
            }}>
        <SgthTable records={data} columns={columnasConsumibles}
          onRowClick={({ record }) => router.push(ROUTES.TIC.CONSUMIBLE(record.id))} />
      </DataState>

      <ConsumibleModal opened={nuevo} onClose={() => setNuevo(false)} onCreado={(id) => router.push(ROUTES.TIC.CONSUMIBLE(id))} />
      <IngresoModal opened={ingreso} onClose={() => setIngreso(false)} />
    </PageShell>
  )
}
