'use client'

import { useState } from 'react'
import { Box, Button, Group, Text, Badge, Select } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconArrowsExchange, IconPlus, IconPlayerStop, IconBan } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { EmptyState } from '@/components/ui/EmptyState'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useSubrogacionesActivas } from '@/features/expediente/hooks/useSubrogaciones'
import { useSubrogacionMutations } from '@/features/expediente/hooks/useSubrogacionMutations'
import { SubrogacionModal } from '@/features/expediente/components/SubrogacionModal'
import { CancelarSubrogacionModal } from '@/features/expediente/components/CancelarSubrogacionModal'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { Subrogacion, TipoSubrogacion, UnidadConRelaciones } from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'

const TIPO_LABELS: Record<TipoSubrogacion, string> = {
  subrogacion: 'Subrogación',
  encargo: 'Encargo',
}

const MOTIVO_LABELS: Record<string, string> = {
  vacaciones: 'Vacaciones',
  comision_servicios: 'Comisión de Servicios',
  enfermedad: 'Enfermedad',
  licencia: 'Licencia',
  encargo_vacante: 'Encargo por Vacante',
  otro: 'Otro',
}

function formatFecha(fecha?: string | null): string {
  if (!fecha) return '—'
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC',
  })
}

function nombreServidor(s?: { nombre?: string; apellido?: string } | null): string {
  if (!s) return '—'
  return [s.apellido, s.nombre].filter(Boolean).join(' ') || '—'
}

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

  const { data: subrogaciones = [], isLoading } = useSubrogacionesActivas({
    unidad_administrativa_id: unidadId ? Number(unidadId) : undefined,
    tipo: (tipo as TipoSubrogacion) || undefined,
  })
  const { finalizar } = useSubrogacionMutations()

  const lista = subrogaciones as Subrogacion[]

  const columns: DataTableColumn<Subrogacion>[] = [
    {
      accessor: 'tipo',
      title: 'Tipo',
      width: 110,
      render: ({ tipo }) => (
        <Badge color={tipo === 'encargo' ? 'blue' : 'grape'} variant="light" size="sm">
          {TIPO_LABELS[tipo]}
        </Badge>
      ),
    },
    {
      accessor: 'subrogante',
      title: 'Subrogante / Encargado',
      render: ({ subrogante }) => (
        <Text size="sm" fw={500}>{nombreServidor(subrogante)}</Text>
      ),
    },
    {
      accessor: 'subrogado',
      title: 'Titular subrogado',
      render: ({ subrogado }) => (
        <Text size="sm" c="dimmed">{subrogado ? nombreServidor(subrogado) : '— (encargo)'}</Text>
      ),
    },
    {
      accessor: 'puesto_subrogado',
      title: 'Puesto',
      render: ({ puesto_subrogado, unidad_administrativa }) => (
        <div>
          <Text size="sm">{puesto_subrogado?.cargo?.nombre ?? '—'}</Text>
          <Text size="xs" c="dimmed">{unidad_administrativa?.nombre ?? '—'}</Text>
        </div>
      ),
    },
    {
      accessor: 'periodo',
      title: 'Período',
      width: 180,
      render: (s) => (
        <Text size="sm">{formatFecha(s.fecha_inicio)} → {formatFecha(s.fecha_fin)}</Text>
      ),
    },
    {
      accessor: 'motivo',
      title: 'Motivo',
      render: ({ motivo }) => (
        <Text size="sm" c="dimmed">{MOTIVO_LABELS[motivo] ?? motivo}</Text>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (s) => (
        <TableActions
          actions={[
            {
              label: 'Finalizar',
              icon: <IconPlayerStop size={14} />,
              color: 'emerald',
              onClick: () => {
                if (confirm('¿Finalizar esta subrogación/encargo?')) {
                  finalizar.mutate(Number(s.id))
                }
              },
            },
            {
              label: 'Cancelar',
              icon: <IconBan size={14} />,
              color: 'red',
              onClick: () => {
                setCancelarId(Number(s.id))
                openCancelar()
              },
            },
          ]}
        />
      ),
    },
  ]

  return (
    <Box>
      <PageHeader
        title="Subrogaciones y Encargos"
        subtitle="Administración de subrogaciones y encargos de puestos vacantes del GAD Provincial de Esmeraldas"
        icon={<IconArrowsExchange size={28} />}
      />

      <Group justify="flex-end" mb="md">
        <Button
          color="emerald" variant="light"
          leftSection={<IconPlus size={16} />}
          onClick={openModal}
        >
          Nueva subrogación / encargo
        </Button>
      </Group>

      <Group gap="sm" mb="md">
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
      </Group>

      {!isLoading && lista.length === 0 ? (
        <EmptyState
          icon={IconArrowsExchange}
          title="Sin subrogaciones/encargos activos"
          description="Los registros activos de subrogación o encargo de puestos aparecerán aquí."
          action={
            <Button color="emerald" variant="light" leftSection={<IconPlus size={14} />} onClick={openModal}>
              Nueva subrogación / encargo
            </Button>
          }
        />
      ) : (
        <SgthTable
          records={lista}
          columns={columns}
          fetching={isLoading}
          minHeight={200}
        />
      )}

      <SubrogacionModal opened={modalOpened} onClose={closeModal} />
      <CancelarSubrogacionModal
        opened={cancelarOpened}
        onClose={() => { setCancelarId(null); closeCancelar() }}
        subrogacionId={cancelarId}
      />
    </Box>
  )
}
