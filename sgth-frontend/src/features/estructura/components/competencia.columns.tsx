import { Text } from '@mantine/core'
import { IconArchive, IconArchiveOff, IconEdit, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import type { Competencia } from '@/types/api'

type Handlers = {
  onEditar:   (c: Competencia) => void
  onRetirar:  (c: Competencia) => void
  onEliminar: (c: Competencia) => void
  /** `gestionar-puestos`. Sin él, el diccionario solo se consulta. */
  puedeGestionar: boolean
}

export const getCompetenciaColumns = (
  { onEditar, onRetirar, onEliminar, puedeGestionar }: Handlers,
): DataTableColumn<Competencia>[] => [
  {
    accessor: 'numero',
    title: 'N.º',
    width: 60,
    render: ({ numero }) => <Text size="sm" ff="monospace">{numero}</Text>,
  },
  {
    accessor: 'nombre',
    title: 'Competencia',
    render: ({ nombre, definicion }) => (
      <div>
        <Text size="sm" fw={500}>{nombre}</Text>
        {definicion && <Text size="xs" c="dimmed" lineClamp={2}>{definicion}</Text>}
      </div>
    ),
  },
  {
    accessor: 'tipo',
    title: 'Tipo',
    width: 120,
    render: ({ tipo_label }) => <StatusBadge>{tipo_label}</StatusBadge>,
  },
  {
    // Los números que el jefe escribe en el FOR-02, uno por nivel.
    accessor: 'numeros_comportamiento',
    title: 'Comportamientos (alto · medio · bajo)',
    width: 190,
    render: ({ numeros_comportamiento: n }) => (
      <Text size="sm" ff="monospace">{n.alto} · {n.medio} · {n.bajo}</Text>
    ),
  },
  {
    accessor: 'puestos_count',
    title: 'Puestos',
    width: 80,
    textAlign: 'right',
    render: ({ puestos_count }) => <Text size="sm">{puestos_count ?? 0}</Text>,
  },
  {
    accessor: 'activo',
    title: 'Estado',
    width: 100,
    render: ({ activo }) => (
      <StatusBadge tone={activo ? 'success' : 'neutral'}>{activo ? 'Vigente' : 'Retirada'}</StatusBadge>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (c) => (
      <TableActions actions={[
        {
          label: 'Editar competencia',
          icon: <IconEdit size={14} />,
          hidden: !puedeGestionar,
          onClick: () => onEditar(c),
        },
        {
          label: c.activo ? 'Retirar del diccionario' : 'Volver a ofrecerla',
          icon: c.activo ? <IconArchive size={14} /> : <IconArchiveOff size={14} />,
          hidden: !puedeGestionar,
          onClick: () => onRetirar(c),
        },
        {
          // La que exige algún puesto se retira; no se borra.
          label: 'Eliminar competencia',
          icon: <IconTrash size={14} />,
          color: 'red',
          hidden: !puedeGestionar,
          disabled: (c.puestos_count ?? 0) > 0,
          onClick: () => onEliminar(c),
        },
      ]} />
    ),
  },
]
