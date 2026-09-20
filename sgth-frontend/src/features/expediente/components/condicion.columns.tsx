import { Text } from '@mantine/core'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, confirmar } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { TIPO_DISCAPACIDAD_LABELS } from '../utils/discapacidad'
import type {
  DiscapacidadServidor,
  EnfermedadCatastroficaServidor,
} from '@/types/api'

type Handlers<T> = {
  onEdit: (item: T) => void
  onDelete: (id: number) => void
}

export const getDiscapacidadesColumns = (
  { onEdit, onDelete }: Handlers<DiscapacidadServidor>,
): DataTableColumn<DiscapacidadServidor>[] => [
  {
    accessor: 'tipo_discapacidad',
    title: 'Tipo',
    render: ({ tipo_discapacidad }) => (
      <Text size="sm">
        {tipo_discapacidad
          ? TIPO_DISCAPACIDAD_LABELS[tipo_discapacidad] ?? tipo_discapacidad
          : '—'}
      </Text>
    ),
  },
  {
    accessor: 'porcentaje',
    title: '%',
    width: 80,
    render: ({ porcentaje }) => <StatusBadge>{porcentaje ?? '-'}%</StatusBadge>,
  },
  {
    accessor: 'numero_carnet_conadis',
    title: 'Carnet CONADIS',
    render: ({ numero_carnet_conadis }) => (
      <Text size="sm" ff="monospace">{numero_carnet_conadis ?? '—'}</Text>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (item) => (
      <TableActions actions={[
        { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => onEdit(item) },
        {
          label: 'Eliminar',
          icon: <IconTrash size={14} />,
          color: 'red',
          onClick: () => confirmar({
            title: 'Eliminar discapacidad',
            message: 'Se eliminará este registro de discapacidad. No se puede deshacer.',
            destructiva: true,
            onConfirm: () => onDelete(Number(item.id)),
          }),
        },
      ]} />
    ),
  },
]

export const getEnfermedadesColumns = (
  { onEdit, onDelete }: Handlers<EnfermedadCatastroficaServidor>,
): DataTableColumn<EnfermedadCatastroficaServidor>[] => [
  {
    accessor: 'tipo_enfermedad',
    title: 'Enfermedad',
    render: ({ tipo_enfermedad }) => (
      <Text size="sm" fw={500}>{tipo_enfermedad ?? '—'}</Text>
    ),
  },
  {
    accessor: 'codigo_cie10',
    title: 'CIE-10',
    width: 90,
    render: ({ codigo_cie10 }) => (
      <Text size="sm" ff="monospace">{codigo_cie10 ?? '—'}</Text>
    ),
  },
  {
    accessor: 'fecha_diagnostico',
    title: 'Diagnóstico',
    width: 110,
    render: ({ fecha_diagnostico }) => (
      <Text size="sm">{formatFecha(fecha_diagnostico)}</Text>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (item) => (
      <TableActions actions={[
        { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => onEdit(item) },
        {
          label: 'Eliminar',
          icon: <IconTrash size={14} />,
          color: 'red',
          onClick: () => confirmar({
            title: 'Eliminar enfermedad catastrófica',
            message: 'Se eliminará este registro de enfermedad catastrófica. No se puede deshacer.',
            destructiva: true,
            onConfirm: () => onDelete(Number(item.id)),
          }),
        },
      ]} />
    ),
  },
]
