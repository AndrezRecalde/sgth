import { Stack, Text } from '@mantine/core'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { TableActions, confirmar } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { TIPO_DISCAPACIDAD_LABELS, gradoDiscapacidad } from '../utils/discapacidad'

type Handlers<T> = {
  onEdit: (item: T) => void
  onDelete: (id: number) => void
}

/**
 * Lo que comparten la discapacidad del servidor y la de una carga familiar.
 * Las columnas son las mismas para las dos: antes el familiar tenía las suyas,
 * sin «Editar» y con el porcentaje en otro formato.
 */
interface DiscapacidadFila {
  id: number | string
  tipo_discapacidad?: string | null
  porcentaje?: number | string | null
  numero_carnet_conadis?: string | null
}

interface EnfermedadFila {
  id: number | string
  tipo_enfermedad?: string | null
  codigo_cie10?: string | null
  fecha_diagnostico?: string | null
}

export const getDiscapacidadesColumns = <T extends DiscapacidadFila>(
  { onEdit, onDelete }: Handlers<T>,
): DataTableColumn<T>[] => [
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
    title: 'Porcentaje',
    width: 160,
    // Llega como decimal («45.00»): en pantalla se lee «45 %», con su grado.
    render: ({ porcentaje }) => porcentaje == null ? <Text size="sm">—</Text> : (
      <Stack gap={0}>
        <Text size="sm">{Number(porcentaje)} %</Text>
        <Text size="xs" c="dimmed">{gradoDiscapacidad(porcentaje) ?? 'Bajo el mínimo'}</Text>
      </Stack>
    ),
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

export const getEnfermedadesColumns = <T extends EnfermedadFila>(
  { onEdit, onDelete }: Handlers<T>,
): DataTableColumn<T>[] => [
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
