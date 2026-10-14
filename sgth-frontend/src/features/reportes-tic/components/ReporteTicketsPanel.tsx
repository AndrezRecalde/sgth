'use client'

import { useState } from 'react'
import { SimpleGrid, Stack } from '@mantine/core'
import { IconCircleCheck, IconClockHour4, IconInbox, IconLifebuoy, IconStar } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable, StatCard, Toolbar } from '@/components/ui'
import { hoyIso } from '@/lib/fecha'
import { useReporteTic } from '../hooks/useReportesTic'
import { DescargasReporte } from './DescargasReporte'
import { RangoFechas, type Rango } from './RangoFechas'
import { columnasPorCategoria, columnasPorTecnico } from './reportes.columns'

const numero = (v: number | null | undefined, sufijo = '') =>
  v === null || v === undefined ? '—' : `${v.toLocaleString('es-EC', { maximumFractionDigits: 1 })}${sufijo}`

/**
 * El trabajo de la mesa de ayuda en un rango, por técnico y por categoría.
 * Recibidos son los abiertos en el rango; resueltos, los resueltos en él.
 */
export function ReporteTicketsPanel() {
  const [rango, setRango] = useState<Rango>(() => ({ desde: `${hoyIso().slice(0, 8)}01`, hasta: hoyIso() }))
  const { data, isLoading, error } = useReporteTic('tickets', rango)
  const t = data?.totales

  return (
    <>
      <Toolbar actions={<DescargasReporte reporte="tickets" parametros={rango} disabled={!data} />}>
        <RangoFechas rango={rango} onChange={setRango} />
      </Toolbar>

      <SimpleGrid cols={{ base: 2, md: 4 }}>
        <StatCard label="Recibidos" value={t?.abiertos ?? 0} icon={IconInbox} loading={isLoading} />
        <StatCard label="Resueltos" value={t?.resueltos ?? 0} icon={IconCircleCheck} loading={isLoading}
          hint={t?.cumplimiento_sla !== null && t ? `${t.cumplimiento_sla} % dentro del plazo` : undefined} />
        <StatCard label="Tiempo de resolución" value={numero(t?.horas_resolucion, ' h')} icon={IconClockHour4} loading={isLoading}
          hint="Promedio en horas hábiles" />
        <StatCard label="Satisfacción" value={t?.satisfaccion ? `${numero(t.satisfaccion)} / 5` : '—'} icon={IconStar} loading={isLoading}
          hint={t ? `${t.encuestas} encuestas` : undefined} />
      </SimpleGrid>

      <DataState loading={isLoading} error={error} empty={!data?.por_tecnico.length && !data?.por_categoria.length}
        emptyProps={{ icon: IconLifebuoy, title: 'Sin tickets en el rango', description: 'Elija otro rango de fechas.' }}>
        <Stack gap="md">
          <SectionCard title="Por técnico" description="También los técnicos registrados que no tuvieron tickets.">
            <SgthTable idAccessor="tecnico" records={data?.por_tecnico ?? []} columns={columnasPorTecnico} />
          </SectionCard>
          <SectionCard title="Por categoría">
            <SgthTable idAccessor="categoria" records={data?.por_categoria ?? []} columns={columnasPorCategoria} />
          </SectionCard>
        </Stack>
      </DataState>
    </>
  )
}
