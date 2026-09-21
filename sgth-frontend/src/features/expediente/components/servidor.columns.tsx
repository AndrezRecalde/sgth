import { Text } from '@mantine/core'
import { IconEdit, IconEye, IconHistory } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { REGIMEN_LABELS } from '@/lib/regimen'
import type { ServidorConRelaciones } from '@/types/api'

type Handlers = {
  onView: (servidor: ServidorConRelaciones) => void
  onEdit: (servidor: ServidorConRelaciones) => void
  onAccionPersonal: (servidor: ServidorConRelaciones) => void
}

export function nombreCompleto(row: ServidorConRelaciones): string {
  return [row.apellido, row.segundo_apellido, row.nombre, row.segundo_nombre]
    .filter(Boolean).join(' ')
}

/**
 * En qué situación está la persona, en una sola columna.
 *
 * Antes había dos: «Vínculo» (interno/externo, que repetía lo que ya dice el
 * régimen) y «Estado», que mostraba ACTIVO incluso a quien nunca fue
 * contratado, porque leía la columna `estado` de la ficha y no el vínculo.
 */
function situacion(row: ServidorConRelaciones) {
  if (!row.estado) return { texto: 'Inactivo', tone: 'neutral' as const }
  if (row.pendiente_vinculacion) return { texto: 'Sin vínculo', tone: 'warning' as const }
  return { texto: 'En funciones', tone: 'success' as const }
}

export const getServidorColumns = (
  { onView, onEdit, onAccionPersonal }: Handlers,
): DataTableColumn<ServidorConRelaciones>[] => [
  {
    accessor: 'cedula',
    title: 'Cédula',
    width: 115,
    render: ({ cedula }) => (
      <Text size="sm" ff="monospace">{cedula ?? '-'}</Text>
    ),
  },
  {
    accessor: 'nombre',
    title: 'Nombre completo',
    render: (row) => (
      <Text size="sm" fw={500}>{nombreCompleto(row) || '-'}</Text>
    ),
  },
  {
    accessor: 'cargo',
    title: 'Cargo',
    render: (row) => (
      <Text size="sm" c="dimmed">
        {row.contrato_vigente?.puesto?.cargo?.nombre ?? row.puesto?.cargo?.nombre ?? '-'}
      </Text>
    ),
  },
  {
    accessor: 'unidad',
    title: 'Unidad',
    width: 190,
    render: (row) => (
      <Text size="sm" c="dimmed">
        {row.contrato_vigente?.unidad_administrativa?.nombre
          ?? row.unidad_administrativa?.nombre
          ?? '-'}
      </Text>
    ),
  },
  {
    accessor: 'regimen_laboral',
    title: 'Régimen',
    // 190: 'Servicios Profesionales' es la etiqueta más larga de las tres y
    // con 110 se leía «SERVICIOS PROFESIO…».
    width: 190,
    render: ({ regimen_laboral }) => {
      if (!regimen_laboral) return <Text size="sm" c="dimmed">-</Text>
      return (
        <StatusBadge>
          {REGIMEN_LABELS[regimen_laboral] ?? regimen_laboral}
        </StatusBadge>
      )
    },
  },
  {
    accessor: 'situacion',
    title: 'Situación',
    width: 120,
    render: (row) => {
      const { texto, tone } = situacion(row)
      return <StatusBadge tone={tone}>{texto}</StatusBadge>
    },
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (servidor) => (
      <TableActions actions={[
        {
          label: 'Ver expediente',
          icon: <IconEye size={14} />,
          onClick: () => onView(servidor),
        },
        {
          label: 'Acción de Personal',
          icon: <IconHistory size={14} />,
          onClick: () => onAccionPersonal(servidor),
        },
        {
          label: 'Editar datos',
          icon: <IconEdit size={14} />,
          onClick: () => onEdit(servidor),
        },
      ]} />
    ),
  },
]
