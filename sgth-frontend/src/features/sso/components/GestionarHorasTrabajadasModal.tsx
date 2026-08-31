'use client'

import { confirmar } from '@/components/ui'
import { useState } from 'react'
import {
  Modal, Stack, Group, TextInput, NumberInput, Button,
  ActionIcon, Text, Select,
} from '@mantine/core'
import { IconTrash, IconPlus } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { SgthTable } from '@/components/ui/SgthTable'
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
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()

  const [periodo, setPeriodo] = useState('')
  const [unidadId, setUnidadId] = useState<string | null>(null)
  const [totalHoras, setTotalHoras] = useState<number | ''>('')

  const { data, isLoading } = useHorasTrabajadas()
  const registros = data?.data ?? []
  const { registrar, eliminar } = useHorasTrabajadasMutations()
  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })
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
    <Modal
      opened={opened}
      onClose={onClose}
      title="Horas trabajadas por período"
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack gap="md">
        <Text size="xs" c="dimmed">
          Cargue manualmente el total de horas trabajadas por período (formato AAAA para un año, o AAAA-MM para
          un mes). Deje la unidad en blanco para registrar el total institucional.
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
            color="emerald"
            loading={registrar.isPending}
            onClick={handleRegistrar}
            disabled={!periodo || !totalHoras}
          >
            Guardar
          </Button>
        </Group>

        <SgthTable
          records={registros}
          columns={columns}
          fetching={isLoading}
          noRecordsText="Sin registros de horas trabajadas todavía."
          minHeight={120}
        />
      </Stack>
    </Modal>
  )
}
