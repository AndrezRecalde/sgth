'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconCalendarEvent, IconCubePlus } from '@tabler/icons-react'
import {
  DataState, MotivoModal, PAGINACION_ES, PageHeader, PageShell, SgthTable,
} from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import {
  FILTROS_INICIALES_MIS_PERMISOS,
  MisPermisosFiltros,
  type FiltrosPantallaMisPermisos,
} from './MisPermisosFiltros'
import { getMisPermisosColumns } from './misPermisos.columns'
import { MisRepososMedicos } from './MisRepososMedicos'
import { PermisoModal } from './PermisoModal'
import { useMisPermisos } from '../hooks/useMisPermisos'
import { useExportarPermiso } from '../hooks/useExportarPermiso'
import { usePermisoMutations } from '../hooks/usePermisoMutations'
import type { PermisoServidor } from '@/types/api'

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
  const { anular } = usePermisoMutations()
  const [anulando, setAnulando] = useState<PermisoServidor | null>(null)

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
    onAnular: setAnulando,
  })

  const periodo = filtros.anio ? `en ${filtros.anio}` : 'registrados'

  return (
    <PageShell>
      <PageHeader
        title="Mis permisos"
        description="Sus permisos de ausencia: el estado, el plazo para entregar el respaldo y el motivo."
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
          title: `No tiene permisos ${periodo}`,
          description: puedeRegistrar
            ? 'Registre uno con «Nuevo permiso», o pruebe con otro año o con otro estado.'
            : 'Los permisos que registre Talento Humano a su nombre aparecerán aquí. ' +
              'Pruebe con otro año o con otro estado.',
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

      <MisRepososMedicos anio={filtros.anio ? Number(filtros.anio) : undefined} />

      {puedeRegistrar && (
        <PermisoModal opened={modalAbierto} onClose={cerrarModal} soloPropio />
      )}

      <MotivoModal
        opened={anulando !== null}
        onClose={() => setAnulando(null)}
        title="Anular permiso"
        confirmLabel="Anular"
        destructiva
        cargando={anular.isPending}
        onConfirm={(motivo) => {
          if (!anulando) return
          anular.mutate(
            { id: anulando.id, motivo },
            { onSuccess: () => setAnulando(null) },
          )
        }}
        descripcion={
          <>
            El permiso <b>{anulando?.folio}</b> quedará anulado y no amparará la
            ausencia. Queda registrado el motivo.
          </>
        }
      />
    </PageShell>
  )
}
