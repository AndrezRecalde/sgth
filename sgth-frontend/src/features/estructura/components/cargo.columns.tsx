import { Text } from '@mantine/core'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { TableActions } from '@/components/ui'
import type { Cargo } from '@/types/api'

type Acciones = {
  alEditar:   (cargo: Cargo) => void
  alEliminar: (cargo: Cargo) => void
  /** `gestionar-puestos`. Sin él, el catálogo solo se consulta. */
  puedeGestionar: boolean
}

/*
| Sin la columna «Estado»: el listado solo trae cargos activos, así que decía
| «Activo» en todas las filas.
*/
export const getCargoColumns = (
  { alEditar, alEliminar, puedeGestionar }: Acciones,
): DataTableColumn<Cargo>[] => [
  {
    accessor: 'nombre',
    title: 'Cargo',
    render: ({ nombre, denominacion_generica }) => (
      <div>
        <Text size="sm" fw={500}>{nombre}</Text>
        {denominacion_generica && <Text size="xs" c="dimmed">{denominacion_generica}</Text>}
      </div>
    ),
  },
  {
    // De aquí lo heredan las fichas médicas ocupacionales de sus puestos.
    accessor: 'codigo_ciuo',
    title: 'CIUO',
    width: 90,
    render: ({ codigo_ciuo }) => <Text size="sm" ff="monospace" c={codigo_ciuo ? undefined : 'dimmed'}>{codigo_ciuo ?? '-'}</Text>,
  },
  {
    accessor: 'puestos_count',
    title: 'Puestos',
    width: 90,
    textAlign: 'right',
    render: ({ puestos_count }) => <Text size="sm">{puestos_count ?? 0}</Text>,
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (cargo) => (
      <TableActions
        actions={[
          {
            label: 'Editar cargo',
            icon: <IconEdit size={14} />,
            hidden: !puedeGestionar,
            onClick: () => alEditar(cargo),
          },
          {
            // Con puestos no se elimina: el backend lo rechazaba después de
            // que la persona confirmara.
            label: 'Eliminar cargo',
            icon: <IconTrash size={14} />,
            color: 'red',
            hidden: !puedeGestionar,
            disabled: (cargo.puestos_count ?? 0) > 0,
            onClick: () => alEliminar(cargo),
          },
        ]}
      />
    ),
  },
]
