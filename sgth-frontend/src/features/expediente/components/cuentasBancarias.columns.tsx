import { Group, Text } from '@mantine/core'
import { IconEdit, IconStar, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, confirmar } from '@/components/ui'
import type { CuentaBancariaConRelaciones } from '@/types/api'

const TIPO_LABELS: Record<string, string> = {
  ahorros: 'Ahorros',
  corriente: 'Corriente',
}

const PROPOSITO_LABELS: Record<string, string> = {
  sueldo: 'Nómina',
  viaticos: 'Viáticos',
  ambos: 'Nómina y viáticos',
}

/** Solo se ofrece ser principal de lo que la cuenta paga. */
const puedeSerPrincipal = (
  c: CuentaBancariaConRelaciones,
  de: 'sueldo' | 'viatico',
): boolean =>
  de === 'sueldo'
    ? !c.es_principal_sueldo && c.proposito !== 'viaticos'
    : !c.es_principal_viatico && c.proposito !== 'sueldo'

type Handlers = {
  onEdit: (cuenta: CuentaBancariaConRelaciones) => void
  onPrincipal: (cuenta: CuentaBancariaConRelaciones, proposito: 'sueldo' | 'viatico') => void
  onDelete: (id: number) => void
}

export const getCuentasBancariasColumns = (
  { onEdit, onPrincipal, onDelete }: Handlers,
): DataTableColumn<CuentaBancariaConRelaciones>[] => [
  {
    accessor: 'entidad_financiera',
    title: 'Entidad financiera',
    render: (c) => (
      <Text size="sm" fw={500}>
        {c.entidad_financiera?.nombre ?? `Entidad ${c.entidad_financiera_id}`}
      </Text>
    ),
  },
  {
    accessor: 'numero_cuenta',
    title: 'Número',
    render: (c) => (
      <Text size="sm" ff="monospace">{c.numero_cuenta ?? '—'}</Text>
    ),
  },
  {
    accessor: 'tipo_cuenta',
    title: 'Tipo',
    width: 110,
    render: (c) => (
      <Text size="sm" c="dimmed">{TIPO_LABELS[c.tipo_cuenta ?? ''] ?? '—'}</Text>
    ),
  },
  {
    accessor: 'proposito',
    title: 'Se usa para',
    width: 210,
    render: (c) => (
      <Group gap="xs">
        <StatusBadge>{PROPOSITO_LABELS[c.proposito ?? 'sueldo']}</StatusBadge>
        {c.es_principal_sueldo && <StatusBadge size="xs" variant="dot">Principal nómina</StatusBadge>}
        {c.es_principal_viatico && <StatusBadge size="xs" variant="dot">Principal viáticos</StatusBadge>}
      </Group>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (c) => (
      <TableActions actions={[
        {
          label: 'Principal para nómina',
          icon: <IconStar size={14} />,
          hidden: !puedeSerPrincipal(c, 'sueldo'),
          onClick: () => onPrincipal(c, 'sueldo'),
        },
        {
          label: 'Principal para viáticos',
          icon: <IconStar size={14} />,
          hidden: !puedeSerPrincipal(c, 'viatico'),
          onClick: () => onPrincipal(c, 'viatico'),
        },
        {
          label: 'Editar cuenta',
          icon: <IconEdit size={14} />,
          onClick: () => onEdit(c),
        },
        {
          label: 'Eliminar cuenta',
          icon: <IconTrash size={14} />,
          color: 'red',
          onClick: () => confirmar({
            title: 'Eliminar cuenta bancaria',
            message: 'Se eliminará esta cuenta bancaria del expediente. No se puede deshacer.',
            destructiva: true,
            onConfirm: () => onDelete(Number(c.id)),
          }),
        },
      ]} />
    ),
  },
]
