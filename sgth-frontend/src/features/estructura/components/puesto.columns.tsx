import { Group, Text } from '@mantine/core'
import { IconArrowsExchange, IconEdit, IconTrash, IconList } from '@tabler/icons-react'
import { StatusBadge, TableActions } from '@/components/ui'
import type { DataTableColumn } from 'mantine-datatable'
import type { PuestoConRelaciones } from '@/types/api'
import { REGIMEN_LABELS } from '@/lib/regimen'

type Handlers = {
  onEdit:        (puesto: PuestoConRelaciones) => void
  onDelete:      (puesto: PuestoConRelaciones) => void
  onActividades: (puesto: PuestoConRelaciones) => void
  /** Pasarlo ocupado a otra unidad, con el acto de cada ocupante (fase 7.1a). */
  onTraspasar:   (puesto: PuestoConRelaciones) => void
  /** `gestionar-puestos`. Sin él, solo se consultan las actividades. */
  puedeGestionar: boolean
  /** `preparar-accion-personal`: el traspaso es un lote de acciones. */
  puedeTraspasar: boolean
}

/** «Vacante», o quiénes lo ocupan: los dos primeros y cuántos más. */
function ocupantesDe(puesto: PuestoConRelaciones): string {
  const nombres = (puesto.ocupantes ?? []).map((o) => o.nombre).filter(Boolean) as string[]

  if (!nombres.length) return puesto.ocupado ? 'Reservado o por regir' : 'Vacante'

  return nombres.length > 2 ? `${nombres.slice(0, 2).join(', ')} y ${nombres.length - 2} más` : nombres.join(', ')
}

export const getPuestoColumns = (
  { onEdit, onDelete, onActividades, onTraspasar, puedeGestionar, puedeTraspasar }: Handlers
): DataTableColumn<PuestoConRelaciones>[] => [
  {
    accessor: 'cargo',
    title: 'Cargo',
    render: ({ cargo, es_jefe }) => (
      <div>
        <Text size="sm" fw={500}>{cargo?.nombre ?? '-'}</Text>
        {es_jefe && (
          <Group gap={4} mt={2}>
            <StatusBadge tone="success" size="xs">Jefe</StatusBadge>
          </Group>
        )}
      </div>
    ),
  },
  {
    accessor: 'unidad_administrativa',
    title: 'Unidad',
    render: ({ unidad_administrativa }) => (
      <Text size="sm">{unidad_administrativa?.nombre ?? '-'}</Text>
    ),
  },
  {
    accessor: 'regimen_laboral',
    title: 'Régimen',
    width: 170,
    render: ({ regimen_laboral }) => regimen_laboral ? (
      <StatusBadge>
        {REGIMEN_LABELS[regimen_laboral] ?? regimen_laboral}
      </StatusBadge>
    ) : <Text size="sm" c="dimmed">-</Text>,
  },
  {
    accessor: 'ocupantes',
    title: 'Ocupantes',
    render: (puesto) => (
      <Text size="sm" c={puesto.ocupado ? undefined : 'dimmed'} lineClamp={2}>{ocupantesDe(puesto)}</Text>
    ),
  },
  {
    accessor: 'plazas',
    title: 'Plazas',
    width: 90,
    render: ({ plazas }) => (
      <Text size="sm" ta="center">{plazas ?? 1}</Text>
    ),
  },
  {
    accessor: 'rmu',
    title: 'RMU',
    width: 100,
    render: ({ rmu }) => (
      <Text size="sm">
        {rmu ? `$${Number(rmu).toFixed(2)}` : '-'}
      </Text>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (puesto) => (
      <TableActions actions={[
        {
          label: 'Actividades del puesto',
          icon: <IconList size={14} />,
          onClick: () => onActividades(puesto),
        },
        {
          label: 'Editar puesto',
          icon: <IconEdit size={14} />,
          hidden: !puedeGestionar,
          onClick: () => onEdit(puesto),
        },
        {
          // Ocupado, cambia de unidad con el acto de cada ocupante (fase 7.1a).
          // La autoridad electa y la jefatura no se traspasan: la jefatura va
          // con su unidad.
          label: 'Traspasar a otra unidad',
          icon: <IconArrowsExchange size={14} />,
          hidden: !puedeTraspasar || !puesto.ocupado || puesto.admite_traspaso === false,
          onClick: () => onTraspasar(puesto),
        },
        {
          // Ni ocupado ni con historia: si no, se desactiva.
          label: 'Eliminar puesto',
          icon: <IconTrash size={14} />,
          color: 'red',
          hidden: !puedeGestionar || puesto.puede_eliminar === false,
          onClick: () => onDelete(puesto),
        },
      ]} />
    ),
  },
]
