'use client'

import { Stack, Group, Text, Badge, Button, Divider } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus } from '@tabler/icons-react'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import { useContratos } from '../../hooks/useContratos'
import { useContratoMutations } from '../../hooks/useContratoMutations'
import { ContratoModal } from '../ContratoModal'
import type { ContratoConRelaciones, EstadoContrato } from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'

const ESTADO_COLORS: Record<EstadoContrato, string> = {
  vigente:   'emerald',
  terminado: 'gray',
  cancelado: 'red',
}

const NOMBRAMIENTO_LABELS: Record<string, string> = {
  nombramiento_permanente:       'Nombramiento permanente',
  nombramiento_provisional:      'Nombramiento provisional',
  servicios_ocasionales:         'Servicios ocasionales',
  libre_nombramiento_remocion:   'Libre nombramiento',
  codigo_trabajo:                'Código del Trabajo',
  servicios_profesionales:       'Servicios profesionales',
}

interface Props {
  servidorId: number
}

export function LaboralTab({ servidorId }: Props) {
  const [modalOpened, { open, close }] = useDisclosure(false)
  const { data: contratos = [], isLoading } = useContratos(servidorId)
  const { eliminar } = useContratoMutations(servidorId)

  const columns: DataTableColumn<ContratoConRelaciones>[] = [
    {
      accessor: 'tipo_nombramiento',
      title: 'Tipo',
      render: ({ tipo_nombramiento }) => (
        <Text size="sm">
          {NOMBRAMIENTO_LABELS[tipo_nombramiento ?? ''] ?? tipo_nombramiento ?? '-'}
        </Text>
      ),
    },
    {
      accessor: 'unidad_administrativa',
      title: 'Unidad',
      render: ({ unidad_administrativa }) => (
        <Text size="sm">{(unidad_administrativa as { nombre?: string })?.nombre ?? '-'}</Text>
      ),
    },
    {
      accessor: 'fecha_inicio',
      title: 'Inicio',
      width: 100,
      render: ({ fecha_inicio }) => (
        <Text size="sm">{fecha_inicio ?? '-'}</Text>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 90,
      render: ({ estado }) => (
        <Badge
          color={ESTADO_COLORS[estado as EstadoContrato] ?? 'gray'}
          variant="light"
          size="sm"
        >
          {estado ?? '-'}
        </Badge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (contrato) => (
        <TableActions actions={[
          {
            label: 'Editar contrato',
            icon: <IconEdit size={14} />,
            color: 'blue',
            onClick: () => {},
          },
          {
            label: 'Eliminar contrato',
            icon: <IconTrash size={14} />,
            color: 'red',
            onClick: () => {
              if (confirm('¿Eliminar este contrato?'))
                eliminar.mutate(Number(contrato.id))
            },
          },
        ]} />
      ),
    },
  ]

  return (
    <Stack gap="md">
      <Group justify="flex-end">
        <Button
          size="xs"
          color="emerald"
          variant="light"
          leftSection={<IconPlus size={14} />}
          onClick={open}
        >
          Nuevo contrato
        </Button>
      </Group>

      <SgthTable
        records={(contratos as ContratoConRelaciones[])}
        columns={columns}
        fetching={isLoading}
        minHeight={120}
      />

      <ContratoModal
        opened={modalOpened}
        onClose={close}
        servidorId={servidorId}
      />
    </Stack>
  )
}
