import { Text, Badge } from '@mantine/core'
import { IconEye, IconEdit, IconHistory } from '@tabler/icons-react'
import { TableActions } from '@/components/ui/TableActions'
import { esExterno } from '../utils/nombramiento'
import type { DataTableColumn } from 'mantine-datatable'
import type { ServidorConRelaciones } from '@/types/api'

const REGIMEN_LABELS: Record<string, string> = {
  losep:          'LOSEP',
  codigo_trabajo: 'Cód. Trabajo',
}

const REGIMEN_COLORS: Record<string, string> = {
  losep:          'emerald',
  codigo_trabajo: 'blue',
}

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
    width:    110,
    render:   ({ regimen_laboral }) => {
      if (!regimen_laboral) return <Text size="sm" c="dimmed">-</Text>
      return (
        <Badge
          color={REGIMEN_COLORS[regimen_laboral] ?? 'gray'}
          variant="light"
          size="sm"
        >
          {REGIMEN_LABELS[regimen_laboral] ?? regimen_laboral}
        </Badge>
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
        <Badge
          color={esExterno(tipo) ? 'orange' : 'gray'}
          variant="light"
          size="sm"
        >
          {esExterno(tipo) ? 'Externo' : 'Interno'}
        </Badge>
      )
    },
  },
  {
    accessor: 'estado',
    title:    'Estado',
    width:    90,
    render:   ({ estado }) => (
      <Badge
        color={estado ? 'emerald' : 'gray'}
        variant="light"
        size="sm"
      >
        {estado ? 'Activo' : 'Inactivo'}
      </Badge>
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
