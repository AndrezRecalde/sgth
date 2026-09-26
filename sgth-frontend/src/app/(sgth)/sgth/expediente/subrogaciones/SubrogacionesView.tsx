'use client'

import { useState } from 'react'
import { Button, Select } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconArrowsExchange, IconPlus } from '@tabler/icons-react'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useSubrogacionesVigentes } from '@/features/expediente/hooks/useSubrogaciones'
import { useSubrogacionMutations } from '@/features/expediente/hooks/useSubrogacionMutations'
import { SubrogacionModal } from '@/features/expediente/components/SubrogacionModal'
import { CancelarSubrogacionModal } from '@/features/expediente/components/CancelarSubrogacionModal'
import { getSubrogacionColumns } from '@/features/expediente/components/subrogaciones.columns'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { Subrogacion, TipoSubrogacion, UnidadConRelaciones } from '@/types/api'
import { DataState, PageHeader, PageShell, SgthTable, Toolbar } from '@/components/ui'

export function SubrogacionesView() {
  const contained = useContainedInput()
  const [modalOpened, { open: openModal, close: closeModal }] = useDisclosure(false)
  const [cancelarOpened, { open: openCancelar, close: closeCancelar }] = useDisclosure(false)
  const [cancelarId, setCancelarId] = useState<number | null>(null)

  const [unidadId, setUnidadId] = useState<string | null>(null)
  const [tipo, setTipo] = useState<string | null>(null)

  const { data: unidadesRaw } = useTodasUnidades({ nivel: 2 })
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[]
  const unidadOptions = unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))

  const { data: subrogaciones = [], isLoading, error } = useSubrogacionesVigentes({
    unidad_administrativa_id: unidadId ? Number(unidadId) : undefined,
    tipo: (tipo as TipoSubrogacion) || undefined,
  })
  const { finalizar } = useSubrogacionMutations()

  const lista = subrogaciones as Subrogacion[]

  const columns = getSubrogacionColumns({
    onFinalizar: (id) => finalizar.mutate(id),
    onCancelar: (id) => { setCancelarId(id); openCancelar() },
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
          data={[
            { value: 'subrogacion', label: 'Subrogación' },
            { value: 'encargo', label: 'Encargo' },
          ]}
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
      <CancelarSubrogacionModal
        opened={cancelarOpened}
        onClose={() => { setCancelarId(null); closeCancelar() }}
        subrogacionId={cancelarId}
      />
    </PageShell>
  )
}
