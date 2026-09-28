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
import { formatFecha } from '@/lib/fecha'
import { useAuth } from '@/hooks/useAuth'
import { TIPO_LABELS, TIPO_OPTIONS } from '@/features/expediente/utils/subrogaciones'
import type { Subrogacion, TipoSubrogacion, UnidadConRelaciones } from '@/types/api'
import {
  DataState, MotivoModal, PageHeader, PageShell, SgthTable, Toolbar,
} from '@/components/ui'

export function SubrogacionesView() {
  const contained = useContainedInput()
  const { hasRole } = useAuth()

  // Quien solo consulta —auditoría y máxima autoridad— ve el listado sin las
  // acciones: el backend las reserva a UATH, así que ofrecerlas era prometer
  // un 403.
  const puedeAdministrar = hasRole('admin-uath') || hasRole('asistente-uath') || hasRole('admin-ti')
  const [modalOpened, { open: openModal, close: closeModal }] = useDisclosure(false)
  const [cancelarOpened, { open: openCancelar, close: closeCancelar }] = useDisclosure(false)
  // El registro entero y no su id: el modal nombra lo que se va a cancelar.
  const [cancelando, setCancelando] = useState<Subrogacion | null>(null)

  const [unidadId, setUnidadId] = useState<string | null>(null)
  const [tipo, setTipo] = useState<string | null>(null)

  const { data: unidadesRaw } = useTodasUnidades({ nivel: 2 })
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[]
  const unidadOptions = unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))

  const { data: subrogaciones = [], isLoading, error } = useSubrogacionesVigentes({
    unidad_administrativa_id: unidadId ? Number(unidadId) : undefined,
    tipo: (tipo as TipoSubrogacion) || undefined,
  })
  const { finalizar, cancelar } = useSubrogacionMutations()

  const lista = subrogaciones as Subrogacion[]

  const cerrarCancelar = () => { setCancelando(null); closeCancelar() }

  const columns = getSubrogacionColumns({
    onFinalizar: (id) => finalizar.mutate(id),
    onCancelar: (id) => {
      setCancelando(lista.find((s) => s.id === id) ?? null)
      openCancelar()
    },
    puedeAdministrar,
  })

  return (
    <PageShell>
      <PageHeader
        title="Subrogaciones y Encargos"
        description="Administración de subrogaciones y encargos de puestos vacantes del GAD Provincial de Esmeraldas"
      />

      <Toolbar
        actions={
          <Button variant="light"
            leftSection={<IconPlus size={16} />}
            onClick={openModal}
          >
            Nueva subrogación / encargo
          </Button>
        }
      >
        <Select
          label="Unidad administrativa"
          placeholder="Todas"
          data={unidadOptions}
          searchable
          clearable
          {...contained}
          value={unidadId}
          onChange={setUnidadId}
          style={{ minWidth: 220 }}
        />
        <Select
          label="Tipo"
          placeholder="Todos"
          data={TIPO_OPTIONS}
          clearable
          {...contained}
          value={tipo}
          onChange={setTipo}
          style={{ minWidth: 180 }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!lista.length}
        emptyProps={{
          icon: IconArrowsExchange,
          title: 'Sin subrogaciones/encargos vigentes',
          description: 'Aquí aparecerán las subrogaciones y encargos pendientes de aprobación y los que ya surten efecto.',
          action: (
            <Button variant="light" leftSection={<IconPlus size={14} />} onClick={openModal}>
              Nueva subrogación / encargo
            </Button>
          ),
        }}
      >
        <SgthTable
          records={lista}
          columns={columns}
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
