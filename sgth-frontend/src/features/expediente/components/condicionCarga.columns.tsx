import { Text } from '@mantine/core'
import { IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { TableActions, confirmar } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { TIPO_DISCAPACIDAD_LABELS } from '../utils/discapacidad'
import type {
  DiscapacidadCargaFamiliar,
  EnfermedadCatastroficaCargaFamiliar,
} from '@/types/api'

type Handlers = {
  onEliminar: (id: number) => void
}

export const getDiscapacidadCargaColumns = ({
  onEliminar,
}: Handlers): DataTableColumn<DiscapacidadCargaFamiliar>[] => [
  {
    accessor: 'tipo_discapacidad',
    title: 'Tipo',
    render: (d) => (
      <Text size="sm">
        {TIPO_DISCAPACIDAD_LABELS[d.tipo_discapacidad] ?? d.tipo_discapacidad}
      </Text>
    ),
  },
  {
    accessor: 'porcentaje',
    title: '%',
    width: 70,
    render: (d) => <Text size="sm">{d.porcentaje}%</Text>,
  },
  {
    accessor: 'numero_carnet_conadis',
    title: 'Carnet CONADIS',
    render: (d) => (
      <Text size="sm" ff="monospace">{d.numero_carnet_conadis ?? '—'}</Text>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (d) => (
      <TableActions actions={[{
        label: 'Eliminar',
        icon: <IconTrash size={14} />,
        color: 'red',
        onClick: () => confirmar({
          title: 'Eliminar discapacidad',
          message: 'Se eliminará esta discapacidad de la carga familiar. No se puede deshacer.',
          destructiva: true,
          onConfirm: () => onEliminar(d.id),
        }),
      }]} />
    ),
  },
]

export const getEnfermedadCargaColumns = ({
  onEliminar,
}: Handlers): DataTableColumn<EnfermedadCatastroficaCargaFamiliar>[] => [
  {
    accessor: 'tipo_enfermedad',
    title: 'Enfermedad',
    render: (e) => <Text size="sm">{e.tipo_enfermedad}</Text>,
  },
  {
    accessor: 'codigo_cie10',
    title: 'CIE-10',
    width: 90,
    render: (e) => (
      <Text size="sm" ff="monospace">{e.codigo_cie10 ?? '—'}</Text>
    ),
  },
  {
    accessor: 'fecha_diagnostico',
    title: 'Diagnóstico',
    width: 120,
    render: (e) => <Text size="sm">{formatFecha(e.fecha_diagnostico)}</Text>,
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (e) => (
      <TableActions actions={[{
        label: 'Eliminar',
        icon: <IconTrash size={14} />,
        color: 'red',
        onClick: () => confirmar({
          title: 'Eliminar enfermedad',
          message: 'Se eliminará esta enfermedad de la carga familiar. No se puede deshacer.',
          destructiva: true,
          onConfirm: () => onEliminar(e.id),
        }),
      }]} />
    ),
  },
]
