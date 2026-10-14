'use client'

import { useState } from 'react'
import { Grid, Select, Text } from '@mantine/core'
import { IconDevices } from '@tabler/icons-react'
import { CountBadge, DataState, SectionCard, SgthTable, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCatalogosFormulario } from '@/features/inventario-ti/hooks/useCatalogosInventario'
import { useReporteTic } from '../hooks/useReportesTic'
import { DescargasReporte } from './DescargasReporte'
import { columnasInventario } from './reportes.columns'

/**
 * El inventario por unidad o por ubicación: a la izquierda cuántos equipos
 * tiene cada una; a la derecha, los de la elegida. El Excel y el PDF llevan
 * todos.
 */
export function ReporteInventarioPanel() {
  const compacto = useContainedInput('sm')
  const [agrupar, setAgrupar] = useState<'unidad' | 'ubicacion'>('unidad')
  const [tipo, setTipo] = useState<string | null>(null)
  const [elegido, setElegido] = useState<string | null>(null)
  const { data: catalogos } = useCatalogosFormulario()
  const parametros = { agrupar, tipo_bien_id: tipo }
  const { data, isLoading, error } = useReporteTic('inventario', parametros)

  const grupos = data?.grupos ?? []
  const grupo = grupos.find((g) => g.nombre === elegido) ?? grupos[0]
  const porUbicacion = agrupar === 'ubicacion'

  return (
    <>
      <Toolbar actions={<DescargasReporte reporte="inventario" parametros={parametros} disabled={!data?.total} />}>
        <Select label="Agrupar por" allowDeselect={false} {...compacto}
          data={[{ value: 'unidad', label: 'Unidad' }, { value: 'ubicacion', label: 'Ubicación' }]}
          value={agrupar} onChange={(v) => { setAgrupar(v === 'ubicacion' ? 'ubicacion' : 'unidad'); setElegido(null) }} />
        <Select label="Tipo" placeholder="Todos" clearable {...compacto}
          data={(catalogos?.tipos ?? []).map((t) => ({ value: String(t.id), label: t.nombre }))}
          value={tipo} onChange={(v) => { setTipo(v); setElegido(null) }} />
      </Toolbar>

      <DataState loading={isLoading} error={error} empty={!grupos.length}
        emptyProps={{ icon: IconDevices, title: 'Sin equipos en servicio', description: 'No hay equipos con estos filtros.' }}>
        <Grid>
          <Grid.Col span={{ base: 12, lg: 4 }}>
            <SectionCard title={porUbicacion ? 'Por ubicación' : 'Por unidad'} description={`${data?.total ?? 0} equipos en servicio. Elija una fila para ver sus equipos.`}>
              <SgthTable
                idAccessor="nombre"
                records={grupos}
                onRowClick={({ record }) => setElegido(record.nombre)}
                rowStyle={(g) => (g.nombre === grupo?.nombre ? { background: 'var(--sgth-accent-light)' } : undefined)}
                columns={[
                  { accessor: 'nombre', title: porUbicacion ? 'Ubicación' : 'Unidad' },
                  { accessor: 'total', title: 'Equipos', width: 100, render: (g) => <CountBadge>{g.total}</CountBadge> },
                ]}
              />
            </SectionCard>
          </Grid.Col>
          <Grid.Col span={{ base: 12, lg: 8 }}>
            <SectionCard title={grupo?.nombre ?? '—'} description={porUbicacion ? 'La unidad es la de quien lo tiene.' : 'La unidad de quien lo tiene; si nadie lo tiene, la del equipo.'}>
              {grupo
                ? <SgthTable records={grupo.equipos} columns={columnasInventario(porUbicacion)} />
                : <Text size="sm" c="dimmed">Elija una fila.</Text>}
            </SectionCard>
          </Grid.Col>
        </Grid>
      </DataState>
    </>
  )
}
