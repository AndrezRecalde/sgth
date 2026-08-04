'use client'

import { useState } from 'react'
import { Badge, Box, Button, Group, Select, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconArrowRight, IconPlus } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useSumarios } from '../hooks/useDisciplinario'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'
import { SumarioModal } from './SumarioModal'
import {
  ESTADO_SUMARIO_COLORS,
  ESTADO_SUMARIO_LABELS,
  SIGUIENTE_HITO_SUMARIO,
  TIPO_SANCION_LABELS,
  formatFecha,
  nombreServidor,
} from '../utils/etiquetas'
import type { EstadoSumario, Sumario } from '@/types/api'

const ESTADO_OPTIONS = (Object.keys(ESTADO_SUMARIO_LABELS) as EstadoSumario[])
  .map((e) => ({ value: e, label: ESTADO_SUMARIO_LABELS[e] }))

export function SumariosTab() {
  const contained = useContainedInput()
  const [estado, setEstado] = useState<string | null>(null)
  const [modalOpened, { open, close }] = useDisclosure(false)

  const { data, isLoading } = useSumarios(
    estado ? { estado: estado as EstadoSumario } : undefined,
  )
  const sumarios = data?.data ?? []

  const { avanzarSumario } = useDisciplinarioMutations()

  const columns: DataTableColumn<Sumario>[] = [
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (s) => (
        <div>
          <Text size="sm" fw={500}>{nombreServidor(s.servidor)}</Text>
          <Text size="xs" c="dimmed">{s.servidor?.cedula ?? '—'}</Text>
        </div>
      ),
    },
    {
      accessor: 'motivo',
      title: 'Motivo',
      render: (s) => (
        <Text size="sm" lineClamp={2}>{s.motivo}</Text>
      ),
    },
    {
      accessor: 'fecha_apertura',
      title: 'Apertura',
      width: 110,
      render: (s) => <Text size="sm">{formatFecha(s.fecha_apertura)}</Text>,
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 140,
      render: (s) => (
        <Badge color={ESTADO_SUMARIO_COLORS[s.estado]} variant="light" size="sm">
          {ESTADO_SUMARIO_LABELS[s.estado]}
        </Badge>
      ),
    },
    {
      accessor: 'sancion',
      title: 'Sanción',
      width: 150,
      render: (s) => s.sancion
        ? (
          <Badge
            color={s.sancion.tipo_sancion === 'destitucion' ? 'red' : 'gray'}
            variant="light"
            size="sm"
          >
            {TIPO_SANCION_LABELS[s.sancion.tipo_sancion]}
          </Badge>
        )
        : <Text size="sm" c="dimmed">—</Text>,
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (s) => {
        const siguiente = SIGUIENTE_HITO_SUMARIO[s.estado]

        if (!siguiente) return null

        return (
          <TableActions
            actions={[
              {
                label: `Avanzar a ${ESTADO_SUMARIO_LABELS[siguiente]}`,
                icon: <IconArrowRight size={14} />,
                color: 'blue',
                onClick: () => avanzarSumario.mutate({
                  id: s.id,
                  data: { estado: siguiente },
                }),
              },
            ]}
          />
        )
      },
    },
  ]

  return (
    <Box>
      <Group justify="space-between" mb="md">
        <Select
          label="Filtrar por estado"
          placeholder="Todos"
          data={ESTADO_OPTIONS}
          value={estado}
          onChange={setEstado}
          clearable
          {...contained}
          style={{ minWidth: 240 }}
        />
        <Button
          leftSection={<IconPlus size={16} />}
          color="emerald"
          variant="light"
          onClick={open}
        >
          Abrir sumario
        </Button>
      </Group>

      <SgthTable
        records={sumarios}
        columns={columns}
        fetching={isLoading}
        minHeight={200}
      />

      <SumarioModal opened={modalOpened} onClose={close} />
    </Box>
  )
}
