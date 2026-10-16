'use client'

import { useState } from 'react'
import { Select, SimpleGrid } from '@mantine/core'
import { IconDroplet, IconListNumbers, IconTruckDelivery } from '@tabler/icons-react'
import { DataState, SgthTable, StatCard, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { hoyIso } from '@/lib/fecha'
import { TIPOS_CONSUMIBLE } from '@/features/consumibles-ti/constants/consumibles'
import type { TipoConsumible } from '@/features/consumibles-ti/services/consumiblesService'
import { useReporteTic } from '../hooks/useReportesTic'
import type { AgrupacionConsumos } from '../services/reportesTicService'
import { DescargasReporte } from './DescargasReporte'
import { RangoFechas, type Rango } from './RangoFechas'
import { columnasConsumos } from './consumibles.columns'

const AGRUPACIONES: { value: AgrupacionConsumos; label: string }[] = [
  { value: 'consumible', label: 'Por consumible' },
  { value: 'area', label: 'Por área' },
  { value: 'impresora', label: 'Por impresora' },
]

const GRUPOS: Record<AgrupacionConsumos, string> = { consumible: 'Consumibles', area: 'Áreas', impresora: 'Impresoras' }

/**
 * El consumo de consumibles en un período (5.4d): las entregas, de TIC y de
 * Bodega, por consumible, por área (la de cada entrega en su momento) o por
 * impresora. El reporte mensual que reemplaza la constancia de entrega [P5].
 */
export function ReporteConsumosPanel() {
  const compacto = useContainedInput('sm')
  const [rango, setRango] = useState<Rango>(() => ({ desde: `${hoyIso().slice(0, 8)}01`, hasta: hoyIso() }))
  const [agrupar, setAgrupar] = useState<AgrupacionConsumos>('consumible')
  const [tipo, setTipo] = useState<TipoConsumible | null>(null)
  const parametros = { ...rango, agrupar, tipo }
  const { data, isLoading, error } = useReporteTic('consumos', parametros)

  return (
    <>
      <Toolbar actions={<DescargasReporte reporte="consumos" parametros={parametros} disabled={!data} />}>
        <RangoFechas rango={rango} onChange={setRango} />
        <Select label="Agrupar" data={AGRUPACIONES} allowDeselect={false} {...compacto}
          value={agrupar} onChange={(v) => setAgrupar(AGRUPACIONES.find((a) => a.value === v)?.value ?? 'consumible')} />
        <Select label="Tipo" placeholder="Todos" data={TIPOS_CONSUMIBLE} clearable {...compacto}
          value={tipo} onChange={(v) => setTipo(TIPOS_CONSUMIBLE.find((t) => t.value === v)?.value ?? null)} />
      </Toolbar>

      <SimpleGrid cols={{ base: 1, xs: 3 }}>
        <StatCard label="Unidades entregadas" value={data?.cantidad ?? 0} icon={IconDroplet} loading={isLoading} />
        <StatCard label="Entregas" value={data?.entregas ?? 0} icon={IconTruckDelivery} loading={isLoading} />
        <StatCard label={GRUPOS[agrupar]} value={data?.grupos.length ?? 0}
          icon={IconListNumbers} loading={isLoading} />
      </SimpleGrid>

      <DataState loading={isLoading} error={error} empty={!data?.grupos.length}
        emptyProps={{ icon: IconDroplet, title: 'Sin entregas en el período', description: 'Elija otro rango de fechas.' }}>
        <SgthTable idAccessor="nombre" records={data?.grupos ?? []} columns={columnasConsumos(data?.agrupado_por ?? agrupar)} />
      </DataState>
    </>
  )
}
