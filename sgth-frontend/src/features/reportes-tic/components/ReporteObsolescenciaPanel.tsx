'use client'

import { useState } from 'react'
import { Select, SimpleGrid, Stack } from '@mantine/core'
import { IconCalendarExclamation, IconDevices, IconHelpCircle, IconHourglassLow } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable, StatCard, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCatalogosFormulario } from '@/features/inventario-ti/hooks/useCatalogosInventario'
import { useReporteTic } from '../hooks/useReportesTic'
import { DescargasReporte } from './DescargasReporte'
import { columnasObsolescencia } from './reportes.columns'

/**
 * Los equipos en servicio que ya cumplieron la vida útil de su tipo o la
 * cumplen en los próximos 12 meses, para planificar el reemplazo.
 */
export function ReporteObsolescenciaPanel() {
  const compacto = useContainedInput('sm')
  const [tipo, setTipo] = useState<string | null>(null)
  const { data: catalogos } = useCatalogosFormulario()
  const parametros = { tipo_bien_id: tipo }
  const { data, isLoading, error } = useReporteTic('obsolescencia', parametros)
  const t = data?.totales

  return (
    <>
      <Toolbar actions={<DescargasReporte reporte="obsolescencia" parametros={parametros} disabled={!t?.en_servicio} />}>
        <Select label="Tipo" placeholder="Todos" clearable {...compacto}
          data={(catalogos?.tipos ?? []).map((x) => ({ value: String(x.id), label: x.nombre }))}
          value={tipo} onChange={setTipo} />
      </Toolbar>

      <SimpleGrid cols={{ base: 2, md: 4 }}>
        <StatCard label="En servicio" value={t?.en_servicio ?? 0} icon={IconDevices} loading={isLoading} />
        <StatCard label="Vida útil cumplida" value={t?.cumplida ?? 0} icon={IconHourglassLow} loading={isLoading}
          tone={t?.cumplida ? 'danger' : undefined} />
        <StatCard label="Se cumple en 12 meses" value={t?.proxima ?? 0} icon={IconCalendarExclamation} loading={isLoading}
          tone={t?.proxima ? 'warning' : undefined} />
        <StatCard label="Sin fin de vida útil" value={t?.sin_fecha ?? 0} icon={IconHelpCircle} loading={isLoading}
          hint="Sin fecha de compra, o el tipo sin años de vida útil" />
      </SimpleGrid>

      <DataState loading={isLoading} error={error} empty={!data?.resumen.length}
        emptyProps={{ icon: IconDevices, title: 'Sin equipos en servicio', description: 'No hay equipos con estos filtros.' }}>
        <Stack gap="md">
          <SectionCard title="Por tipo" description="Los años de vida útil de cada tipo se definen en Configuración.">
            <SgthTable
              idAccessor="tipo"
              records={data?.resumen ?? []}
              columns={[
                { accessor: 'tipo', title: 'Tipo' },
                { accessor: 'anios_vida_util', title: 'Vida útil', width: 120, render: (r) => (r.anios_vida_util ? `${r.anios_vida_util} años` : 'Sin definir') },
                { accessor: 'en_servicio', title: 'En servicio', width: 120 },
                { accessor: 'cumplida', title: 'Cumplida', width: 110 },
                { accessor: 'proxima', title: 'En 12 meses', width: 120 },
                { accessor: 'sin_fecha', title: 'Sin fin de vida útil', width: 170 },
              ]}
            />
          </SectionCard>
          <SectionCard title="Equipos para reemplazo" description="Del que cumplió antes. Correctivos: los de los últimos 12 meses, sin anulados.">
            <SgthTable records={data?.equipos ?? []} columns={columnasObsolescencia}
              noRecordsText="Ningún equipo cumplió su vida útil ni la cumple en los próximos 12 meses." />
          </SectionCard>
        </Stack>
      </DataState>
    </>
  )
}
