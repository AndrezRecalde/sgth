import Link from 'next/link'
import { Anchor, Group, Text } from '@mantine/core'
import { IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import type { ModeloCompatibleFormData } from '../schemas/consumible.schema'
import type { DetalleConsumible } from '../services/consumiblesService'

type ModeloEnEdicion = ModeloCompatibleFormData & { id: string }

/** Los modelos que se están armando en el formulario del consumible. */
export const columnasModelosEnEdicion = ({ nombreMarca, onQuitar }: {
  nombreMarca: (id: number) => string
  onQuitar: (id: string) => void
}): DataTableColumn<ModeloEnEdicion>[] => [
  { accessor: 'marca_id', title: 'Marca', width: 160, render: (m) => nombreMarca(m.marca_id) },
  { accessor: 'modelo', title: 'Modelo' },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (m) => <TableActions actions={[{ label: 'Quitar', icon: <IconTrash size={14} />, color: 'red', onClick: () => onQuitar(m.id) }]} />,
  },
]

type ModeloConEquipos = DetalleConsumible['modelos'][number]

/**
 * Los modelos compatibles en el detalle, con los equipos del inventario que
 * coinciden. Un modelo sin equipos es la señal de que alguna ficha lo escribe
 * distinto.
 */
export const columnasModelosCompatibles = ({ onQuitar }: { onQuitar?: (m: ModeloConEquipos) => void }): DataTableColumn<ModeloConEquipos>[] => [
  {
    accessor: 'modelo',
    title: 'Modelo',
    width: 200,
    render: (m) => <Text size="sm" fw={500}>{[m.marca, m.modelo].filter(Boolean).join(' ')}</Text>,
  },
  {
    accessor: 'equipos',
    title: 'Equipos del inventario',
    width: 260,
    render: (m) => (m.equipos.length
      ? (
        <Group gap="xs">
          {m.equipos.map((e) => (
            <Anchor key={e.id} size="sm" component={Link} href={ROUTES.TIC.EQUIPO(e.id)} title={[e.custodio, e.ubicacion].filter(Boolean).join(' · ')}>
              {e.codigo_institucional}
            </Anchor>
          ))}
        </Group>
      )
      : <StatusBadge tone="warning" variant="dot">Ningún equipo con ese modelo: revise cómo lo escribe la ficha</StatusBadge>),
  },
  ...(onQuitar
    ? [{
        accessor: 'acciones',
        title: '',
        width: 50,
        render: (m: ModeloConEquipos) => (
          <TableActions actions={[{ label: 'Quitar de los compatibles', icon: <IconTrash size={14} />, color: 'red', onClick: () => onQuitar(m) }]} />
        ),
      }]
    : []),
]
