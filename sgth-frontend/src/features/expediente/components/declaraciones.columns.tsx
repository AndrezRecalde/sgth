import { Text } from '@mantine/core'
import { IconDownload, IconEdit, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, confirmar } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { DeclaracionJuramentada } from '@/types/api'

const TIPO_LABELS: Record<string, string> = {
  inicio_gestion: 'Inicio de gestión',
  periodica: 'Periódica',
  fin_gestion: 'Fin de gestión',
}

type Handlers = {
  onVerDocumento: (id: number) => void
  onEdit: (item: DeclaracionJuramentada) => void
  onDelete: (id: number) => void
}

export const getDeclaracionesColumns = (
  { onVerDocumento, onEdit, onDelete }: Handlers,
): DataTableColumn<DeclaracionJuramentada>[] => [
  {
    accessor: 'tipo_declaracion',
    title: 'Tipo',
    width: 110,
    render: ({ tipo_declaracion }) => (
      <StatusBadge>{TIPO_LABELS[tipo_declaracion] ?? tipo_declaracion}</StatusBadge>
    ),
  },
  {
    accessor: 'fecha_declaracion',
    title: 'Fecha',
    width: 110,
    render: ({ fecha_declaracion }) => (
      <Text size="sm">{formatFecha(fecha_declaracion)}</Text>
    ),
  },
  {
    accessor: 'codigo_barras',
    title: 'Código',
    render: ({ codigo_barras }) => (
      <Text size="sm" ff="monospace">{codigo_barras ?? '-'}</Text>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (item) => (
      <TableActions actions={[
        {
          // Solo aparece si la declaración tiene su PDF escaneado.
          label: 'Ver documento',
          icon: <IconDownload size={14} />,
          hidden: !item.documento_ruta,
          onClick: () => onVerDocumento(item.id),
        },
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
            title: 'Eliminar declaración',
            message: 'Se eliminará esta declaración patrimonial del expediente. No se puede deshacer.',
            destructiva: true,
            onConfirm: () => onDelete(Number(item.id)),
          }),
        },
      ]} />
    ),
  },
]
