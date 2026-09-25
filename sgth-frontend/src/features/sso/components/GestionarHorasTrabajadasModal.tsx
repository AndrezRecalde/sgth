'use client'

import { confirmar, DataState, SgthModal, SgthTable } from '@/components/ui'
import { useState } from 'react'
import {
  Stack, Group, TextInput, NumberInput, Button,
  ActionIcon, Text, Select,
} from '@mantine/core'
import { IconTrash, IconPlus, IconClock } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useHorasTrabajadas, useHorasTrabajadasMutations } from '../hooks/useHorasTrabajadas'
import type { UnidadConRelaciones } from '@/types/api'
import type { HorasTrabajadasPeriodo } from '../services/ssoService'
import type { DataTableColumn } from 'mantine-datatable'

interface Props {
  opened: boolean
  onClose: () => void
}

export function GestionarHorasTrabajadasModal({ opened, onClose }: Props) {
  const contained = useContainedInput()

  const [periodo, setPeriodo] = useState('')
  const [unidadId, setUnidadId] = useState<string | null>(null)
  const [totalHoras, setTotalHoras] = useState<number | ''>('')

  const { data, isLoading, error, refetch } = useHorasTrabajadas()
  const registros = data?.data ?? []
  const { registrar, eliminar } = useHorasTrabajadasMutations()
  const { data: unidades = [], error: errorUnidades } = useTodasUnidades({ nivel: 2 })
  const unidadOptions = ((unidades ?? []) as UnidadConRelaciones[]).map(u => ({
    value: String(u.id), label: u.nombre ?? `Unidad ${u.id}`,
  }))

  const handleRegistrar = () => {
    if (!periodo || !totalHoras) return
    registrar.mutate({
      periodo,
      unidad_administrativa_id: unidadId ? Number(unidadId) : undefined,
      total_horas: Number(totalHoras),
    }, {
      onSuccess: () => {
        setPeriodo('')
        setUnidadId(null)
        setTotalHoras('')
      },
    })
  }

  const columns: DataTableColumn<HorasTrabajadasPeriodo>[] = [
    { accessor: 'periodo', title: 'Período', width: 110 },
    {
      accessor: 'unidad_administrativa',
      title: 'Unidad',
      render: (r) => r.unidad_administrativa?.nombre ?? 'Total institucional',
    },
    {
      accessor: 'total_horas',
      title: 'Horas',
      width: 110,
      render: (r) => r.total_horas.toLocaleString(),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (r) => (
        <ActionIcon
          color="red"
          variant="subtle"
          onClick={() => confirmar({
            title:   'Eliminar registro de horas',
            message: 'Se eliminará este registro de horas trabajadas. No se puede deshacer.',
            destructiva: true,
            onConfirm: () => eliminar.mutate(r.id),
          })}
        >
          <IconTrash size={16} />
        </ActionIcon>
      ),
    },
  ]

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title="Horas trabajadas por período"
      size="lg"
    >
      <Stack gap="md">
        <Text size="xs" c="dimmed">
          Cargue manualmente el total de horas trabajadas por período (formato AAAA para un año, o AAAA-MM para
          un mes). Deje la unidad en blanco para registrar el total institucional. Si carga los meses y consulta
          el año, los índices CD 513 suman los meses cargados; el total institucional manda sobre las unidades.
        </Text>
        <Group align="flex-end" wrap="nowrap">
          <TextInput
            label="Período"
            placeholder="2026 o 2026-07"
            style={{ flex: 1 }}
            {...contained}
            value={periodo}
            onChange={(e) => setPeriodo(e.currentTarget.value)}
          />
          <Select
            label="Unidad (opcional)"
            placeholder="Total institucional"
            data={unidadOptions}
            searchable
            clearable
            style={{ flex: 1 }}
            {...contained}
            value={unidadId}
            onChange={setUnidadId}
            // Sin esto, un catálogo que no cargó se ve como «no hay unidades».
            error={errorUnidades ? 'No se pudieron cargar las unidades administrativas.' : undefined}
          />
          <NumberInput
            label="Total de horas"
            min={1}
            style={{ width: 150 }}
            {...contained}
            value={totalHoras}
            onChange={(v) => setTotalHoras(typeof v === 'number' ? v : '')}
          />
          <Button
            leftSection={<IconPlus size={16} />}
            loading={registrar.isPending}
            onClick={handleRegistrar}
            disabled={!periodo || !totalHoras}
          >
            Guardar
          </Button>
        </Group>

        <DataState
          loading={isLoading}
          error={error}
          errorTitle="No se pudieron cargar las horas trabajadas"
          errorHint="No quiere decir que no haya períodos cargados: no se pudieron consultar."
          onRetry={() => refetch()}
          skeletonRows={3}
          empty={!registros.length}
          emptyProps={{
            icon: IconClock,
            title: 'Sin registros de horas trabajadas',
            description: 'Cargue el total de horas del período con el formulario de arriba: es el denominador de los índices CD 513.',
          }}
        >
          <SgthTable
            records={registros}
            columns={columns}
            minHeight={120}
          />
        </DataState>
      </Stack>
    </SgthModal>
  )
}
