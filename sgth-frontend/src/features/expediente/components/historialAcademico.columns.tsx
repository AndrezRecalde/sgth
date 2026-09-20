import { Text } from '@mantine/core'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, confirmar } from '@/components/ui'
import type { HistorialAcademicoServidor } from '@/types/api'

const NIVEL_LABELS: Record<string, string> = {
  primaria: 'Primaria',
  secundaria: 'Secundaria',
  tercer_nivel: 'Tercer nivel (Pregrado)',
  cuarto_nivel: 'Cuarto nivel (Posgrado)',
}

/** Solo el año: el período de un título se lee «2018 - 2022». */
const anio = (fecha?: string | null) => (fecha ? fecha.substring(0, 4) : null)

type Handlers = {
  onEdit: (item: HistorialAcademicoServidor) => void
  onDelete: (id: number) => void
}

export const getHistorialAcademicoColumns = (
  { onEdit, onDelete }: Handlers,
): DataTableColumn<HistorialAcademicoServidor>[] => [
  {
    accessor: 'titulo_capacitacion',
    title: 'Título / Capacitación',
    render: ({ titulo_capacitacion, nivel_estudio }) => (
      <div>
        <Text size="sm" fw={500}>{titulo_capacitacion ?? '-'}</Text>
        {nivel_estudio && (
          <Text size="xs" c="dimmed">
            {NIVEL_LABELS[nivel_estudio] ?? nivel_estudio}
          </Text>
        )}
      </div>
    ),
  },
  {
    accessor: 'institucion',
    title: 'Institución',
    render: ({ institucion, nacionalidad_estudio }) => (
      <div>
        <Text size="sm">{institucion ?? '-'}</Text>
        <Text size="xs" c="dimmed">
          {nacionalidad_estudio === 'nacional' ? 'Nacional' : 'Internacional'}
        </Text>
      </div>
    ),
  },
  {
    accessor: 'tipo_estudio',
    title: 'Tipo',
    width: 120,
    render: ({ tipo_estudio }) => (
      <StatusBadge size="xs">
        {tipo_estudio === 'estudio' ? 'Título Académico' : 'Capacitación'}
      </StatusBadge>
    ),
  },
  {
    accessor: 'fecha_inicio',
    title: 'Período',
    width: 150,
    render: ({ fecha_inicio, fecha_fin }) => (
      <Text size="sm">{`${anio(fecha_inicio) ?? '-'} - ${anio(fecha_fin) ?? 'Presente'}`}</Text>
    ),
  },
  {
    accessor: 'codigo_senescyt',
    title: 'SENESCYT',
    width: 120,
    render: ({ codigo_senescyt }) => (
      <Text size="sm" ff="monospace">{codigo_senescyt || '-'}</Text>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (item) => (
      <TableActions actions={[
        {
          label: 'Editar',
          icon: <IconEdit size={14} />,
          onClick: () => onEdit(item),
        },
        {
          label: 'Eliminar',
          icon: <IconTrash size={14} />,
          color: 'red',
          onClick: () => confirmar({
            title: 'Eliminar registro académico',
            message: 'Se eliminará este registro académico del expediente. No se puede deshacer.',
            destructiva: true,
            onConfirm: () => onDelete(Number(item.id)),
          }),
        },
      ]} />
    ),
  },
]
