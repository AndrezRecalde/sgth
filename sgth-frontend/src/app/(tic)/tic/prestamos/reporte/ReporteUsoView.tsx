'use client'

import { useState } from 'react'
import { Button, SimpleGrid } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconAlarm, IconClockHour4, IconFileSpreadsheet, IconPresentation, IconUserX } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell, SgthTable, StatCard, Toolbar } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { columnasReporteUso } from '@/features/prestamos-ti/components/reporteUso.columns'
import { useExcelReporteUso, useReporteUso } from '@/features/prestamos-ti/hooks/usePrestamosTic'

/** El primer día del mes en curso, `YYYY-MM-DD`. */
const inicioDeMes = () => `${hoyIso().slice(0, 8)}01`

/**
 * El uso de cada equipo de préstamo en un rango: cuántas veces salió, cuántas
 * horas estuvo fuera, cuántas volvió tarde y cuántas reservas no se retiraron.
 * Para decidir si hacen falta más proyectores o si uno casi no se pide.
 */
export function ReporteUsoView() {
  const compacto = useContainedInput('sm')
  const [desde, setDesde] = useState(inicioDeMes)
  const [hasta, setHasta] = useState(hoyIso)
  const { data, isLoading, error } = useReporteUso(desde, hasta)
  const excel = useExcelReporteUso()
  const t = data?.totales

  return (
    <PageShell>
      <PageHeader
        title="Uso de los equipos de préstamo"
        description="Cuenta los préstamos entregados en el rango; las horas de uno que sigue fuera llegan hasta ahora"
        backHref={ROUTES.TIC.PRESTAMOS}
        actions={(
          <Button variant="light" leftSection={<IconFileSpreadsheet size={16} />} loading={excel.isPending}
            disabled={!data} onClick={() => excel.mutate({ desde, hasta })}>
            Descargar Excel
          </Button>
        )}
      />
      <Toolbar>
        <DatePickerInput label="Desde" valueFormat="DD/MM/YYYY" maxDate={hasta} {...compacto}
          value={toDateValue(desde)} onChange={(d) => setDesde(fromDateValueOrNull(d) ?? desde)} />
        <DatePickerInput label="Hasta" valueFormat="DD/MM/YYYY" minDate={desde} {...compacto}
          value={toDateValue(hasta)} onChange={(d) => setHasta(fromDateValueOrNull(d) ?? hasta)} />
      </Toolbar>

      <SimpleGrid cols={{ base: 2, md: 4 }}>
        <StatCard label="Préstamos" value={t?.prestamos ?? 0} icon={IconPresentation} loading={isLoading} />
        <StatCard label="Horas fuera" value={t?.horas.toLocaleString('es-EC') ?? 0} icon={IconClockHour4} loading={isLoading} />
        <StatCard label="Devueltos tarde" value={t?.devoluciones_tarde ?? 0} icon={IconAlarm} loading={isLoading}
          tone={t && t.devoluciones_tarde > 0 ? 'danger' : undefined} />
        <StatCard label="Reservas no retiradas" value={t?.no_retirados ?? 0} icon={IconUserX} loading={isLoading} />
      </SimpleGrid>

      <DataState
        loading={isLoading}
        error={error}
        empty={!data?.equipos.length}
        emptyProps={{ icon: IconPresentation, title: 'No hay equipos de préstamo', description: 'Marque un equipo como prestable desde su ficha.' }}
      >
        <SgthTable records={data?.equipos ?? []} columns={columnasReporteUso} minHeight={160} />
      </DataState>
    </PageShell>
  )
}
