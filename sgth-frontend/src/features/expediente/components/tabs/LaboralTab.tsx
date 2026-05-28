'use client'

import { useState } from 'react'
import { Stack, Group, Text, Badge, Button, Skeleton } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconEdit, IconTrash,
         IconBriefcase } from '@tabler/icons-react'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
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

const ESTADO_LABELS: Record<EstadoContrato, string> = {
  vigente:   'Vigente',
  terminado: 'Terminado',
  cancelado: 'Cancelado',
}

const NOMBRAMIENTO_LABELS: Record<string, string> = {
  nombramiento_permanente:     'Nombramiento Permanente',
  nombramiento_provisional:    'Nombramiento Provisional',
  servicios_ocasionales:       'Servicios Ocasionales',
  libre_nombramiento_remocion: 'Libre Nombramiento y Remoción',
  codigo_trabajo:              'Código del Trabajo',
  servicios_profesionales:     'Servicios Profesionales',
}

function formatFecha(fecha?: string | null): string {
  if (!fecha) return '-'
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC'
  })
}

interface Props { servidorId: number }

export function LaboralTab({ servidorId }: Props) {
  const [modalOpened, { open, close }] = useDisclosure(false)
  const [editContrato, setEditContrato] =
    useState<ContratoConRelaciones | null>(null)

  const { data: contratos = [], isLoading } = useContratos(servidorId)
  const { eliminar } = useContratoMutations(servidorId)

  const handleClose = () => {
    setEditContrato(null)
    close()
  }

  const columns: DataTableColumn<ContratoConRelaciones>[] = [
    {
      accessor: 'tipo_nombramiento',
      title: 'Tipo de nombramiento',
      render: ({ tipo_nombramiento }) => (
        <div>
          <Text size="sm" fw={500}>
            {NOMBRAMIENTO_LABELS[tipo_nombramiento ?? '']
              ?? tipo_nombramiento ?? '-'}
          </Text>
        </div>
      ),
    },
    {
      accessor: 'unidad_administrativa',
      title: 'Unidad',
      render: ({ unidad_administrativa }) => (
        <Text size="sm" c="dimmed">
          {(unidad_administrativa as { nombre?: string })?.nombre ?? '-'}
        </Text>
      ),
    },
    {
      accessor: 'fecha_inicio',
      title: 'Inicio',
      width: 110,
      render: ({ fecha_inicio }) => (
        <Text size="sm">{formatFecha(fecha_inicio)}</Text>
      ),
    },
    {
      accessor: 'fecha_fin',
      title: 'Fin',
      width: 110,
      render: ({ fecha_fin }) => (
        <Text size="sm" c={fecha_fin ? undefined : 'dimmed'}>
          {fecha_fin ? formatFecha(fecha_fin) : 'Indefinido'}
        </Text>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 100,
      render: ({ estado }) => (
        <Badge
          color={ESTADO_COLORS[estado as EstadoContrato] ?? 'gray'}
          variant="light"
          size="sm"
        >
          {ESTADO_LABELS[estado as EstadoContrato] ?? estado ?? '-'}
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
            onClick: () => {
              setEditContrato(contrato)
              open()
            },
          },
          {
            label: 'Eliminar contrato',
            icon: <IconTrash size={14} />,
            color: 'red',
            onClick: () => {
              if (confirm('¿Eliminar este contrato? Esta acción no se puede deshacer.'))
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
          onClick={() => { setEditContrato(null); open() }}
        >
          Nuevo contrato
        </Button>
      </Group>

      {isLoading ? (
        <Skeleton height={120} radius="md" />
      ) : Array.isArray(contratos) && contratos.length > 0 ? (
        <SgthTable
          records={contratos as ContratoConRelaciones[]}
          columns={columns}
          fetching={false}
          minHeight={120}
        />
      ) : (
        <EmptyState
          icon={IconBriefcase}
          title="Sin contratos registrados"
          description="Registra el primer contrato o nombramiento del servidor."
        />
      )}

      <ContratoModal
        opened={modalOpened}
        onClose={handleClose}
        servidorId={servidorId}
        contrato={editContrato}
      />
    </Stack>
  )
}
