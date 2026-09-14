'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconCalendarEvent, IconCubePlus } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import {
  FILTROS_INICIALES_MIS_PERMISOS,
  MisPermisosFiltros,
  type FiltrosPantallaMisPermisos,
} from './MisPermisosFiltros'
import { getMisPermisosColumns } from './misPermisos.columns'
import { PermisoModal } from './PermisoModal'
import { useMisPermisos } from '../hooks/useMisPermisos'
import { useExportarPermiso } from '../hooks/useExportarPermiso'

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15

/**
 * «Mis permisos» del Portal del Servidor.
 *
 * El menú del portal llevaba a esta ruta y la página no existía: el enlace
 * terminaba en un 404, y el servidor no tenía dónde ver sus propios permisos
 * sin pedírselos a Talento Humano.
 *
 * Después solo listaba: registrar uno seguía exigiendo pasar por Talento
 * Humano, aunque el backend deja a cada servidor registrar los suyos
 * (`crear-permiso`, entre los permisos base). «Nuevo permiso» abre el mismo
 * modal del SGTH, limitado al permiso propio.
 */
export function MisPermisosView() {
  const [page, setPage] = useState(1)
  const [filtros, setFiltros] = useState<FiltrosPantallaMisPermisos>(
    FILTROS_INICIALES_MIS_PERMISOS,
  )
  const [modalAbierto, { open: abrirModal, close: cerrarModal }] = useDisclosure(false)
  const { hasPermiso } = useAuth()
  const puedeRegistrar = hasPermiso('crear-permiso')
  const { exportar, exportandoId } = useExportarPermiso()

  // Cambiar un filtro sin volver a la primera página consultaría esa página
  // del resultado ya filtrado, casi siempre vacía.
  const cambiarFiltros = (cambio: Partial<FiltrosPantallaMisPermisos>) => {
    setFiltros((actuales) => ({ ...actuales, ...cambio }))
    setPage(1)
  }

  const { data, isLoading, error } = useMisPermisos({
    page,
    per_page: POR_PAGINA,
    estado: filtros.estado === 'todos' ? undefined : filtros.estado,
    anio: filtros.anio ? Number(filtros.anio) : undefined,
  })

  const lista = data?.data ?? []

  const columns = getMisPermisosColumns({
    exportandoId,
    onExportar: (id) => exportar(id),
  })

  const periodo = filtros.anio ? `en ${filtros.anio}` : 'registrados'

  return (
    <PageShell>
      <PageHeader
        title="Mis permisos"
        description="Tus permisos de ausencia: su estado, el plazo para entregar el respaldo y el motivo."
        actions={
          puedeRegistrar && (
            <Button
              variant="light"
              leftSection={<IconCubePlus size={16} />}
              onClick={abrirModal}
            >
              Nuevo permiso
            </Button>
          )
        }
      />

      <MisPermisosFiltros filtros={filtros} onCambiar={cambiarFiltros} />

      <DataState
        loading={isLoading}
        error={error}
        empty={!lista.length}
        emptyProps={{
          icon: IconCalendarEvent,
          title: `No tienes permisos ${periodo}`,
          description: puedeRegistrar
            ? 'Registra uno con «Nuevo permiso», o prueba con otro año o con otro estado.'
            : 'Los permisos que registre Talento Humano a tu nombre aparecerán aquí. ' +
              'Prueba con otro año o con otro estado.',
        }}
      >
        <SgthTable
          // Solo `paginationText`: el objeto entero exige además las opciones
          // de registros por página, que esta tabla no ofrece.
          paginationText={PAGINACION_ES.paginationText}
          records={lista}
          columns={columns}
          totalRecords={data?.total ?? lista.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>

      {puedeRegistrar && (
        <PermisoModal opened={modalAbierto} onClose={cerrarModal} soloPropio />
      )}
    </PageShell>
  )
}
