'use client'

import { useState } from 'react'
import { Button, Select, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconCubePlus } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, SgthTable, Toolbar, confirmar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAuth } from '@/hooks/useAuth'
import type { PuestoConRelaciones } from '@/types/api'
import { usePuestos } from '../hooks/usePuestos'
import { usePuestoMutations } from '../hooks/usePuestoMutations'
import { useTodasUnidades } from '../hooks/useUnidades'
import { getPuestoColumns } from './puesto.columns'
import { PuestoModal } from './PuestoModal'
import { PuestoPerfilDrawer } from './PuestoPerfilDrawer'
import { TraspasoPuestoModal } from './TraspasoPuestoModal'
import { RevisionClasificacionModal } from './RevisionClasificacionModal'

const POR_PAGINA = 15

/** El puesto que se desactiva en vez de eliminarse: se distingue y se filtra. */
const ESTADOS = [
  { value: 'activos',   label: 'Activos' },
  { value: 'inactivos', label: 'Inactivos' },
]

export function PuestosTab() {
  // Crear, editar y borrar puestos y su perfil pide `gestionar-puestos`;
  // quien solo tiene `ver-estructura` (dirección, jefaturas, auditoría)
  // consulta sin ver botones que el backend le negaría.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-puestos')
  // El traspaso de un puesto ocupado es un lote de acciones de personal
  // (fase 7.1a): lo prepara quien prepara acciones. Y la revisión de su
  // clasificación (fase 7.1b).
  const puedeTraspasar = hasPermiso('preparar-accion-personal')
  const filtro = useContainedInput('sm')

  const [pagina, setPagina] = useState(1)
  const [unidadId, setUnidadId] = useState<string | null>(null)
  const [estado, setEstado] = useState<string | null>(null)
  const [enEdicion, setEnEdicion] = useState<PuestoConRelaciones | null>(null)
  const [modalAbierto, modal] = useDisclosure(false)
  const [enPerfil, setEnPerfil] = useState<PuestoConRelaciones | null>(null)
  const [perfilAbierto, perfil] = useDisclosure(false)
  const [aTraspasar, setATraspasar] = useState<PuestoConRelaciones | null>(null)
  const [aRevisar, setARevisar] = useState<PuestoConRelaciones | null>(null)

  const { eliminar } = usePuestoMutations()
  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })
  const { data, isLoading, error } = usePuestos({
    page: pagina,
    per_page: POR_PAGINA,
    unidad_administrativa_id: unidadId ? Number(unidadId) : undefined,
    activo: estado ? estado === 'activos' : undefined,
  })
  const puestos = data?.data ?? []
  const opcionesUnidad = unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))

  // Volver a la primera página al filtrar: si no, se pedía esa misma página
  // del resultado ya recortado —casi siempre inexistente— y la pantalla decía
  // que no había puestos donde sí los había.
  const filtrar = (aplicar: () => void) => {
    aplicar()
    setPagina(1)
  }

  const abrirNuevo = () => {
    setEnEdicion(null)
    modal.open()
  }

  const nuevoPuesto = puedeGestionar && (
    <Button leftSection={<IconCubePlus size={16} />} variant="light" onClick={abrirNuevo}>
      Nuevo puesto
    </Button>
  )

  return (
    <Stack gap="md">
      <Toolbar actions={nuevoPuesto}>
        <Select
          label="Unidad"
          placeholder="Todas las unidades"
          data={opcionesUnidad}
          searchable
          clearable
          {...filtro}
          style={{ minWidth: 300 }}
          value={unidadId}
          onChange={(v) => filtrar(() => setUnidadId(v))}
        />
        <Select
          label="Estado"
          placeholder="Todos"
          data={ESTADOS}
          clearable
          {...filtro}
          style={{ minWidth: 160 }}
          value={estado}
          onChange={(v) => filtrar(() => setEstado(v))}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!puestos.length}
        emptyProps={{
          icon: IconCubePlus,
          title: 'Sin puestos',
          description: unidadId || estado
            ? 'Ningún puesto coincide con el filtro. Quite el filtro para ver los demás.'
            : 'Aún no hay puestos en la plantilla institucional. Cree el primero con «Nuevo puesto».',
          action: !unidadId && !estado ? nuevoPuesto || undefined : undefined,
        }}
        page={pagina}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={puestos}
          columns={getPuestoColumns({
            alEditar: (p) => { setEnEdicion(p); modal.open() },
            alEliminar: (p) => confirmar({
              title: 'Eliminar puesto',
              message: <>Se eliminará el puesto <b>{p.cargo?.nombre ?? '—'}</b>. No se puede deshacer.</>,
              destructiva: true,
              onConfirm: () => eliminar.mutate(Number(p.id)),
            }),
            alVerPerfil: (p) => { setEnPerfil(p); perfil.open() },
            alTraspasar: setATraspasar,
            alRevisar: setARevisar,
            puedeGestionar,
            puedeTraspasar,
          })}
          totalRecords={data?.total ?? puestos.length}
          recordsPerPage={POR_PAGINA}
          page={pagina}
          onPageChange={setPagina}
          minHeight={200}
        />
      </DataState>

      <PuestoModal
        opened={modalAbierto}
        onClose={() => { setEnEdicion(null); modal.close() }}
        puesto={enEdicion}
      />
      <TraspasoPuestoModal puesto={aTraspasar} onClose={() => setATraspasar(null)} />
      <RevisionClasificacionModal puesto={aRevisar} onClose={() => setARevisar(null)} />
      <PuestoPerfilDrawer
        opened={perfilAbierto}
        onClose={() => { setEnPerfil(null); perfil.close() }}
        puesto={enPerfil}
        soloLectura={!puedeGestionar}
      />
    </Stack>
  )
}
