'use client'

import { useState } from 'react'
import { Stack } from '@mantine/core'
import { IconBeach } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, SgthTable } from '@/components/ui'
import { EquipoEstadoChips } from './EquipoEstadoChips'
import { getVacacionesColumns } from './vacaciones.columns'
import { ESTADO_LABELS, FILTROS_ESTADO, TONO_ESTADO } from './vacaciones.constants'
import { useVacaciones } from '../hooks/useVacaciones'
import { useExportarVacacion } from '../hooks/useExportarVacacion'

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15

const SIN_ACCION = () => {}

/**
 * Las vacaciones del equipo, en consulta. Aprobar, rechazar y anular siguen
 * siendo de Talento Humano: aquí no se ofrecen ni a quien además lo es.
 */
export function EquipoVacacionesTab() {
  const [page, setPage] = useState(1)
  const [estado, setEstado] = useState('todos')
  const { exportar, exportandoId } = useExportarVacacion()

  const { data, isLoading, error } = useVacaciones({
    page,
    per_page: POR_PAGINA,
    equipo:   1,
    estado:   estado === 'todos' ? undefined : estado,
  })

  const lista = data?.data ?? []

  const columns = getVacacionesColumns({
    exportandoId,
    puedeResolver: false,
    onExportar:    (id) => exportar(id),
    onAprobar:     SIN_ACCION,
    onRechazar:    SIN_ACCION,
    onAnular:      SIN_ACCION,
  })

  return (
    <Stack gap="md">
      <EquipoEstadoChips
        estados={FILTROS_ESTADO}
        etiquetas={ESTADO_LABELS}
        tonos={TONO_ESTADO}
        valor={estado}
        onCambiar={(nuevo) => {
          setEstado(nuevo)
          setPage(1)
        }}
      />

      <DataState
        loading={isLoading}
        error={error}
        empty={!lista.length}
        emptyProps={{
          icon: IconBeach,
          title: 'Sin vacaciones de su equipo',
          description: estado === 'todos'
            ? 'Aquí aparecen las solicitudes de vacaciones de su unidad y las que lo nombran jefe inmediato.'
            : 'No hay solicitudes en este estado. Elija «Todos» para ver el resto.',
        }}
        page={page}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={lista}
          columns={columns}
          totalRecords={data?.total ?? lista.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>
    </Stack>
  )
}
