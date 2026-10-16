'use client'

import { useState } from 'react'
import { SimpleGrid } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconAlertTriangle, IconDroplet, IconStack2 } from '@tabler/icons-react'
import { DataState, SgthTable, StatCard, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { useReporteTic } from '../hooks/useReportesTic'
import { DescargasReporte } from './DescargasReporte'
import { columnasSaldoConsumibles } from './consumibles.columns'

/**
 * El saldo de lo que guarda TIC al cierre de una fecha (5.4d), para cuadrar
 * con Bodega en el cierre del año. Lo que despacha Bodega no lleva saldo aquí.
 */
export function ReporteSaldoConsumiblesPanel() {
  const compacto = useContainedInput('sm')
  const [fecha, setFecha] = useState(hoyIso)
  const { data, isLoading, error } = useReporteTic('saldo-consumibles', { fecha })

  return (
    <>
      <Toolbar actions={<DescargasReporte reporte="saldo-consumibles" parametros={{ fecha }} disabled={!data} />}>
        <DatePickerInput label="Al cierre del" valueFormat="DD/MM/YYYY" maxDate={hoyIso()} {...compacto}
          value={toDateValue(fecha)} onChange={(d) => setFecha(fromDateValueOrNull(d) ?? fecha)} />
      </Toolbar>

      <SimpleGrid cols={{ base: 1, xs: 3 }}>
        <StatCard label="Consumibles" value={data?.consumibles ?? 0} icon={IconDroplet} loading={isLoading} hint="Los que guarda TIC" />
        <StatCard label="Unidades" value={data?.unidades ?? 0} icon={IconStack2} loading={isLoading} />
        <StatCard label="Bajo el mínimo" value={data?.bajo_minimo ?? 0} icon={IconAlertTriangle} loading={isLoading}
          tone={data?.bajo_minimo ? 'warning' : undefined} />
      </SimpleGrid>

      <DataState loading={isLoading} error={error} empty={!data?.filas.length}
        emptyProps={{ icon: IconStack2, title: 'Sin saldo a esa fecha', description: 'TIC no guardaba consumibles ese día.' }}>
        <SgthTable records={data?.filas ?? []} columns={columnasSaldoConsumibles} />
      </DataState>
    </>
  )
}
