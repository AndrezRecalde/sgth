'use client'

import { useState } from 'react'
import { Select } from '@mantine/core'
import { IconClipboardHeart } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useSolicitudesCertificacion } from '@/features/dispensario/hooks/useSolicitudCertificacion'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { getCertificacionesColumns } from '@/features/dispensario/components/solicitudes-certificacion.columns'
import type { UnidadConRelaciones } from '@/types/api'
import {
  DataState, PageHeader, PageShell, PAGINACION_ES, SgthTable, Toolbar,
} from '@/components/ui'

const ANIO_ACTUAL = new Date().getFullYear()

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15

function generarOpcionesAnio(): { value: string; label: string }[] {
  const opciones = [{ value: '', label: 'Todos los años' }]
  for (let anio = ANIO_ACTUAL; anio >= ANIO_ACTUAL - 5; anio--) {
    opciones.push({ value: String(anio), label: String(anio) })
  }
  return opciones
}

export function CertificacionesMedicasView() {
  const contained = useContainedInput('sm')

  const [page, setPage] = useState(1)
  const [filtroEstado, setFiltroEstado] = useState<string>('')
  const [filtroUnidad, setFiltroUnidad] = useState<string>('')
  const [filtroAnio, setFiltroAnio] = useState<string>(String(ANIO_ACTUAL))

  const { data: unidades = [] } = useTodasUnidades()
  const unidadOptions = [
    { value: '', label: 'Todas las unidades' },
    ...((unidades ?? []) as UnidadConRelaciones[]).map(u => ({
      value: String(u.id),
      label: u.nombre ?? `Unidad ${u.id}`,
    })),
  ]

  const { data, isLoading, error } = useSolicitudesCertificacion({
    page,
    per_page: POR_PAGINA,
    estado: filtroEstado || undefined,
    unidad_administrativa_id: filtroUnidad ? Number(filtroUnidad) : undefined,
    anio: filtroAnio ? Number(filtroAnio) : undefined,
  })

  const solicitudes = data?.data ?? []

  // Cambiar un filtro sin volver a la primera página consultaría esa misma
  // página del resultado ya filtrado —casi siempre vacía—, así que la tabla
  // saldría en blanco aunque hubiera coincidencias.
  const filtrar = (aplicar: () => void) => {
    aplicar()
    setPage(1)
  }

  const columns = getCertificacionesColumns()

  return (
    <PageShell>
      <PageHeader
        title="Certificaciones médicas"
        description="Vista de solo lectura de las solicitudes enviadas al Dispensario. La atención médica se gestiona desde allí."
      />

      <Toolbar>
        <Select
          label="Estado"
          placeholder="Todas"
          data={[
            { value: '',           label: 'Todas'       },
            { value: 'pendiente',  label: 'Pendientes'  },
            { value: 'en_proceso', label: 'En proceso'  },
            { value: 'completada', label: 'Completadas' },
            { value: 'cancelada',  label: 'Canceladas'  },
          ]}
          style={{ minWidth: 180 }}
          {...contained}
          value={filtroEstado}
          onChange={(v) => filtrar(() => setFiltroEstado(v ?? ''))}
        />
        <Select
          label="Unidad administrativa"
          placeholder="Todas las unidades"
          data={unidadOptions}
          searchable
          style={{ minWidth: 240 }}
          {...contained}
          value={filtroUnidad}
          onChange={(v) => filtrar(() => setFiltroUnidad(v ?? ''))}
        />
        <Select
          label="Año"
          placeholder="Todos los años"
          data={generarOpcionesAnio()}
          style={{ minWidth: 150 }}
          {...contained}
          value={filtroAnio}
          onChange={(v) => filtrar(() => setFiltroAnio(v ?? ''))}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!solicitudes.length}
        emptyProps={{
          icon: IconClipboardHeart,
          title: 'Sin solicitudes',
          description: 'No se han enviado solicitudes de certificación médica.',
        }}
      >
        <SgthTable
          // Solo `paginationText`: el objeto entero no compila, porque
          // `recordsPerPageLabel` exige `recordsPerPageOptions` y
          // `onRecordsPerPageChange`.
          paginationText={PAGINACION_ES.paginationText}
          records={solicitudes}
          columns={columns}
          totalRecords={data?.total ?? solicitudes.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>
    </PageShell>
  )
}
