'use client'

import { useState } from 'react'
import { SimpleGrid, Stack } from '@mantine/core'
import { IconMessageCircle, IconMoodSmile, IconStar } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable, StatCard, Toolbar } from '@/components/ui'
import type { SemanticTone } from '@/config/design.tokens'
import { hoyIso } from '@/lib/fecha'
import { Reparto } from '@/features/tablero-tic/components/Reparto'
import { useReporteTic } from '../hooks/useReportesTic'
import { DescargasReporte } from './DescargasReporte'
import { RangoFechas, type Rango } from './RangoFechas'
import { columnasComentarios } from './reportes.columns'

const NOTAS: { nota: '5' | '4' | '3' | '2' | '1'; tono: SemanticTone }[] = [
  { nota: '5', tono: 'success' }, { nota: '4', tono: 'success' }, { nota: '3', tono: 'warning' },
  { nota: '2', tono: 'danger' }, { nota: '1', tono: 'danger' },
]

const promedio = (v: number | null) => (v === null ? '—' : v.toLocaleString('es-EC', { maximumFractionDigits: 1 }))

/** Lo que dijeron los usuarios al confirmar sus tickets: notas, por técnico, por categoría y comentarios. */
export function ReporteSatisfaccionPanel() {
  const [rango, setRango] = useState<Rango>(() => ({ desde: `${hoyIso().slice(0, 8)}01`, hasta: hoyIso() }))
  const { data, isLoading, error } = useReporteTic('satisfaccion', rango)

  return (
    <>
      <Toolbar actions={<DescargasReporte reporte="satisfaccion" parametros={rango} disabled={!data?.encuestas} />}>
        <RangoFechas rango={rango} onChange={setRango} />
      </Toolbar>

      <DataState loading={isLoading} error={error} empty={!data?.encuestas}
        emptyProps={{ icon: IconMoodSmile, title: 'Sin encuestas en el rango', description: 'Las encuestas llegan cuando el usuario confirma su ticket.' }}>
        <Stack gap="md">
          <SimpleGrid cols={{ base: 1, md: 3 }}>
            <StatCard label="Promedio" value={data?.promedio ? `${promedio(data.promedio)} / 5` : '—'} icon={IconStar}
              tone={data?.promedio !== null && data?.promedio !== undefined && data.promedio < 3.5 ? 'warning' : undefined} />
            <StatCard label="Encuestas" value={data?.encuestas ?? 0} icon={IconMoodSmile} />
            <StatCard label="Con comentario" value={data?.comentarios.length ?? 0} icon={IconMessageCircle} />
          </SimpleGrid>

          <SimpleGrid cols={{ base: 1, lg: 3 }}>
            <SectionCard title="Notas">
              <Reparto vacio="Sin encuestas."
                filas={NOTAS.map(({ nota, tono }) => ({ clave: nota, etiqueta: nota === '1' ? '1 estrella' : `${nota} estrellas`, total: data?.distribucion[nota] ?? 0, tono }))} />
            </SectionCard>
            <SectionCard title="Por técnico">
              <SgthTable idAccessor="tecnico" records={data?.por_tecnico ?? []} minHeight={100}
                columns={[{ accessor: 'tecnico', title: 'Técnico' }, { accessor: 'encuestas', title: 'Encuestas', width: 100 }, { accessor: 'promedio', title: 'Promedio', width: 100, render: (r) => promedio(r.promedio) }]} />
            </SectionCard>
            <SectionCard title="Por categoría">
              <SgthTable idAccessor="categoria" records={data?.por_categoria ?? []} minHeight={100}
                columns={[{ accessor: 'categoria', title: 'Categoría' }, { accessor: 'encuestas', title: 'Encuestas', width: 100 }, { accessor: 'promedio', title: 'Promedio', width: 100, render: (r) => promedio(r.promedio) }]} />
            </SectionCard>
          </SimpleGrid>

          <SectionCard title="Comentarios" description="Lo que escribieron al confirmar, en orden de llegada.">
            <SgthTable records={data?.comentarios ?? []} columns={columnasComentarios}
              noRecordsText="Nadie dejó comentarios en el rango." />
          </SectionCard>
        </Stack>
      </DataState>
    </>
  )
}
