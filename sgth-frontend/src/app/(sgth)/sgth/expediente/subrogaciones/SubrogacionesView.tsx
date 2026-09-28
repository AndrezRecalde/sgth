'use client'

import { useState } from 'react'
import { Button, Select } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconArrowsExchange, IconPlus } from '@tabler/icons-react'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useSubrogacionesVigentes } from '@/features/expediente/hooks/useSubrogaciones'
import { useSubrogacionMutations } from '@/features/expediente/hooks/useSubrogacionMutations'
import { SubrogacionModal } from '@/features/expediente/components/SubrogacionModal'
import { getSubrogacionColumns } from '@/features/expediente/components/subrogaciones.columns'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAuth } from '@/hooks/useAuth'
import { formatFecha } from '@/lib/fecha'
import { TIPO_LABELS, TIPO_OPTIONS } from '@/features/expediente/utils/subrogaciones'
import type { Subrogacion, TipoSubrogacion } from '@/types/api'
import {
  DataState, MotivoModal, PAGINACION_ES, PageHeader, PageShell, SgthTable, Toolbar,
} from '@/components/ui'

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15

export function SubrogacionesView() {
  // La variante compacta: en una barra de filtros los campos conviven con
  // botones y no necesitan el aire de un formulario de captura (regla 06).
  const contained = useContainedInput('sm')
  const { hasRole } = useAuth()

  // Quien solo consulta —auditoría y máxima autoridad— ve el listado sin las
  // acciones: el backend las reserva a UATH, así que ofrecerlas era prometer
  // un 403.
  const puedeAdministrar = hasRole('admin-uath') || hasRole('asistente-uath') || hasRole('admin-ti')

  const [modalOpened, { open: openModal, close: closeModal }] = useDisclosure(false)
  const [cancelarOpened, { open: openCancelar, close: closeCancelar }] = useDisclosure(false)
  // El registro entero y no su id: el modal nombra lo que se va a cancelar.
  const [cancelando, setCancelando] = useState<Subrogacion | null>(null)

  const [page, setPage] = useState(1)
  const [unidadId, setUnidadId] = useState<string | null>(null)
  const [tipo, setTipo] = useState<string | null>(null)

  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })
  const unidadOptions = unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))

  const hayFiltros = Boolean(unidadId || tipo)

  const { data, isLoading, error } = useSubrogacionesVigentes({
    page,
    per_page: POR_PAGINA,
    unidad_administrativa_id: unidadId ? Number(unidadId) : undefined,
    tipo: (tipo as TipoSubrogacion) || undefined,
  })

  const lista = data?.data ?? []

  const { finalizar, cancelar } = useSubrogacionMutations()

  // Cambiar un filtro sin volver a la primera página consultaría esa misma
  // página del resultado ya filtrado —casi siempre vacía—, así que la tabla
  // saldría en blanco aunque hubiera coincidencias.
  const filtrar = (aplicar: () => void) => {
    aplicar()
    setPage(1)
  }

  const cerrarCancelar = () => { setCancelando(null); closeCancelar() }

  const columns = getSubrogacionColumns({
    onFinalizar: (id) => finalizar.mutate(id),
    onCancelar: (id) => {
      setCancelando(lista.find((s) => s.id === id) ?? null)
      openCancelar()
    },
    puedeAdministrar,
  })

  const botonNueva = (
    <Button variant="light" leftSection={<IconPlus size={16} />} onClick={openModal}>
      Nueva subrogación / encargo
    </Button>
  )

  return (
    <PageShell>
      <PageHeader
        title="Subrogaciones y Encargos"
        description="Administración de subrogaciones y encargos de puestos vacantes del GAD Provincial de Esmeraldas"
        // La acción principal de la pantalla va aquí, no en la Toolbar, cuyas
        // acciones son las ligadas al conjunto —exportar, limpiar— (regla 06).
        actions={puedeAdministrar ? botonNueva : undefined}
      />

      <Toolbar>
        <Select
          label="Unidad administrativa"
          placeholder="Todas"
          data={unidadOptions}
          searchable
          clearable
          style={{ minWidth: 220 }}
          {...contained}
          value={unidadId}
          onChange={(v) => filtrar(() => setUnidadId(v))}
        />
        <Select
          label="Tipo"
          placeholder="Todos"
          data={TIPO_OPTIONS}
          clearable
          style={{ minWidth: 180 }}
          {...contained}
          value={tipo}
          onChange={(v) => filtrar(() => setTipo(v))}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudo cargar el listado de subrogaciones"
        empty={!lista.length}
        page={page}
        emptyProps={
          // Un vacío con filtros puestos no es el mismo vacío: ofrecer «crear
          // la primera» cuando lo que pasa es que el filtro no encuentra nada
          // manda a registrar algo que quizá ya existe.
          hayFiltros
            ? {
                icon: IconArrowsExchange,
                title: 'Ninguna coincide con el filtro',
                description: 'Pruebe con otra unidad administrativa o con el otro tipo.',
                action: (
                  <Button
                    variant="subtle"
                    onClick={() => filtrar(() => { setUnidadId(null); setTipo(null) })}
                  >
                    Quitar los filtros
                  </Button>
                ),
              }
            : {
                icon: IconArrowsExchange,
                title: 'Sin subrogaciones/encargos vigentes',
                description: 'Aquí aparecerán las subrogaciones y encargos pendientes de aprobación y los que ya surten efecto.',
                action: puedeAdministrar ? botonNueva : undefined,
              }
        }
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

      <SubrogacionModal opened={modalOpened} onClose={closeModal} />

      <MotivoModal
        opened={cancelarOpened}
        onClose={cerrarCancelar}
        title="Cancelar subrogación / encargo"
        descripcion={
          cancelando
            ? (
                <>
                  Se cancelará {TIPO_LABELS[cancelando.tipo].toLowerCase()} de{' '}
                  <b>{cancelando.puesto_subrogado?.cargo?.nombre ?? 'el puesto asignado'}</b>{' '}
                  ({formatFecha(cancelando.fecha_inicio)} — {formatFecha(cancelando.fecha_fin)}).
                  Si su Acción de Personal ya estaba registrada, el subrogante deja de
                  poder firmar.
                </>
              )
            : 'Se cancelará el registro seleccionado.'
        }
        confirmLabel="Cancelar registro"
        destructiva
        cargando={cancelar.isPending}
        onConfirm={(motivo) => {
          if (!cancelando) return
          cancelar.mutate({ id: cancelando.id, motivo }, { onSuccess: cerrarCancelar })
        }}
      />
    </PageShell>
  )
}
