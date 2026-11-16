'use client'

import { useState } from 'react'
import { Stack } from '@mantine/core'
import { IconClipboardList } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, SgthTable } from '@/components/ui'
import { EquipoEstadoChips } from './EquipoEstadoChips'
import { getPermisosColumns } from './permisos.columns'
import { ESTADO_LABELS, FILTROS_ESTADO, TONO_ESTADO } from './permisos.constants'
import { usePermisos } from '../hooks/usePermisos'
import { useExportarPermiso } from '../hooks/useExportarPermiso'
import type { AccionesPermiso } from '../hooks/useAccionesPermiso'

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15

/**
 * En «Mi equipo» el jefe consulta e imprime, nada más: el permiso lo firma en
 * papel. Tampoco confirma ni anula aquí quien además es de Talento Humano;
 * eso sigue en su pantalla del SGTH.
 */
const SOLO_IMPRIMIR: AccionesPermiso = {
  confirmar:     false,
  rechazar:      false,
  validarTs:     false,
  revertir:      false,
  anular:        () => false,
  aprobarSirha7: () => false,
}

const SIN_ACCION = () => {}

/** Los permisos del equipo: los de su unidad y los que lo nombran jefe inmediato. */
export function EquipoPermisosTab() {
  const [page, setPage] = useState(1)
  const [estado, setEstado] = useState('todos')
  const { exportar, exportandoId } = useExportarPermiso()

  const { data, isLoading, error } = usePermisos({
    page,
    per_page: POR_PAGINA,
    equipo:   1,
    estado:   estado === 'todos' ? undefined : estado,
  })

  const lista = data?.data ?? []

  const columns = getPermisosColumns({
    exportandoId,
    puede:           SOLO_IMPRIMIR,
    onExportar:      (id) => exportar(id),
    onConfirmar:     SIN_ACCION,
    onValidarTs:     SIN_ACCION,
    onAnular:        SIN_ACCION,
    onRechazar:      SIN_ACCION,
    onRevertir:      SIN_ACCION,
    onAprobarSirha7: SIN_ACCION,
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
          icon: IconClipboardList,
          title: 'Sin permisos de su equipo',
          description: estado === 'todos'
            ? 'Aquí aparecen los permisos de su unidad y los que lo nombran jefe inmediato.'
            : 'No hay permisos en este estado. Elija «Todos» para ver el resto.',
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
