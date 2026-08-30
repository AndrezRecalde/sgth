import { Text } from '@mantine/core'
import { IconEye, IconEdit, IconHistory } from '@tabler/icons-react'
import { StatusBadge, TableActions } from '@/components/ui'
import { esExterno } from '../utils/nombramiento'
import type { DataTableColumn } from 'mantine-datatable'
import type { ServidorConRelaciones } from '@/types/api'
import { REGIMEN_LABELS, REGIMEN_TONOS } from '@/lib/regimen'

type Handlers = {
  onView: (servidor: ServidorConRelaciones) => void
  onEdit: (servidor: ServidorConRelaciones) => void
  onAccionPersonal: (servidor: ServidorConRelaciones) => void
}

export const getServidorColumns = (
  { onView, onEdit, onAccionPersonal }: Handlers
): DataTableColumn<ServidorConRelaciones>[] => [
  {
    accessor: 'cedula',
    title:    'Cédula',
    width:    115,
    render:   ({ cedula }) => (
      <Text size="sm" ff="monospace">{cedula ?? '-'}</Text>
    ),
  },
  {
    accessor: 'nombre',
    title:    'Nombre completo',
    render:   (row) => {
      const nombre = [
        row.apellido,
        row.segundo_apellido,
        row.nombre,
        row.segundo_nombre,
      ].filter(Boolean).join(' ')
      return (
        <Text size="sm" fw={500}>
          {nombre || '-'}
        </Text>
      )
    },
  },
  {
    accessor: 'cargo',
    title:    'Cargo',
    render:   (row) => {
      // Primero intenta desde contrato_vigente, luego desde puesto directo
      const cargoContrato = row.contrato_vigente?.puesto?.cargo?.nombre
      const cargoPuesto   = row.puesto?.cargo?.nombre
      const cargo         = cargoContrato ?? cargoPuesto

      return (
        <Text size="sm" c="dimmed">
          {cargo ?? '-'}
        </Text>
      )
    },
  },
  {
    accessor: 'regimen_laboral',
    title:    'Régimen',
    // 190: 'Servicios Profesionales' es la etiqueta más larga de las tres y
    // con 110 se leía «SERVICIOS PROFESIO…».
    width:    190,
    render:   ({ regimen_laboral }) => {
      if (!regimen_laboral) return <Text size="sm" c="dimmed">-</Text>
      return (
        <StatusBadge tone={REGIMEN_TONOS[regimen_laboral] ?? 'neutral'}>
          {REGIMEN_LABELS[regimen_laboral] ?? regimen_laboral}
        </StatusBadge>
      )
    },
  },
  {
    accessor: 'tipo_vinculo',
    title:    'Vínculo',
    width:    90,
    render:   (row) => {
      const tipo = row.contrato_vigente?.tipo_nombramiento
      if (!tipo) return <Text size="sm" c="dimmed">-</Text>
      return (
        <StatusBadge tone={esExterno(tipo) ? 'warning' : 'neutral'}>
          {esExterno(tipo) ? 'Externo' : 'Interno'}
        </StatusBadge>
      )
    },
  },
  {
    accessor: 'estado',
    title:    'Estado',
    width:    90,
    render:   ({ estado }) => (
      <StatusBadge tone={estado ? 'success' : 'neutral'}>
        {estado ? 'Activo' : 'Inactivo'}
      </StatusBadge>
    ),
  },
  {
    accessor: 'acciones',
    title:    '',
    width:    50,
    render:   (servidor) => (
      <TableActions actions={[
        {
          label:   'Ver expediente',
          icon:    <IconEye size={14} />,
          color:   'blue',
          onClick: () => onView(servidor),
        },
        {
          label:   'Acción de Personal',
          icon:    <IconHistory size={14} />,
          color:   'emerald',
          onClick: () => onAccionPersonal(servidor),
        },
        {
          label:   'Editar datos',
          icon:    <IconEdit size={14} />,
          color:   'gray',
          onClick: () => onEdit(servidor),
        },
      ]} />
    ),
  },
]
