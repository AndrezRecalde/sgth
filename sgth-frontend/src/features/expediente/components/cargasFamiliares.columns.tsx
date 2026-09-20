import { Group, Text } from '@mantine/core'
import { IconEdit, IconToggleLeft, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, confirmar } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { CargaFamiliar } from '@/types/api'

const PARENTESCO_LABELS: Record<string, string> = {
  // La columna guarda «conyugue» desde el primer día; se muestra bien escrito.
  conyugue: 'Cónyuge',
  conyuge: 'Cónyuge',
  hijo: 'Hijo/a',
  padre: 'Padre',
  madre: 'Madre',
  hermano: 'Hermano/a',
  otro: 'Otro',
}

type Handlers = {
  onEdit: (carga: CargaFamiliar) => void
  onToggleEstado: (id: number) => void
  onDelete: (id: number) => void
}

export const getCargasFamiliaresColumns = (
  { onEdit, onToggleEstado, onDelete }: Handlers,
): DataTableColumn<CargaFamiliar>[] => [
  {
    accessor: 'nombres',
    title: 'Familiar',
    render: (c) => (
      <div>
        <Text size="sm" fw={600}>{`${c.apellidos ?? ''} ${c.nombres ?? ''}`.trim()}</Text>
        <Text size="xs" c="dimmed" ff="monospace">CI: {c.cedula}</Text>
      </div>
    ),
  },
  {
    accessor: 'parentesco',
    title: 'Parentesco',
    width: 110,
    render: (c) => (
      <StatusBadge size="xs">
        {PARENTESCO_LABELS[c.parentesco ?? ''] ?? c.parentesco ?? '—'}
      </StatusBadge>
    ),
  },
  {
    accessor: 'fecha_nacimiento',
    title: 'Nacimiento',
    width: 110,
    render: (c) => <Text size="sm">{formatFecha(c.fecha_nacimiento)}</Text>,
  },
  {
    accessor: 'condiciones',
    title: 'Condiciones',
    width: 150,
    render: (c) => {
      if (!c.persona_con_discapacidad && !c.posee_enfermedad_catastrofica) {
        return <Text size="sm" c="dimmed">—</Text>
      }
      return (
        <Group gap="xs">
          {c.persona_con_discapacidad && (
            <StatusBadge size="xs" variant="dot">Discapacidad</StatusBadge>
          )}
          {c.posee_enfermedad_catastrofica && (
            <StatusBadge size="xs" variant="dot">Enf. catastrófica</StatusBadge>
          )}
        </Group>
      )
    },
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 90,
    render: (c) => (
      <StatusBadge tone={c.estado ? 'success' : 'neutral'}>
        {c.estado ? 'Activa' : 'Inactiva'}
      </StatusBadge>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (c) => (
      <TableActions actions={[
        { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => onEdit(c) },
        {
          label: c.estado ? 'Desactivar' : 'Activar',
          icon: <IconToggleLeft size={14} />,
          onClick: () => onToggleEstado(Number(c.id)),
        },
        {
          label: 'Eliminar',
          icon: <IconTrash size={14} />,
          color: 'red',
          onClick: () => confirmar({
            title: 'Eliminar carga familiar',
            message: 'Se eliminará esta carga familiar del expediente. No se puede deshacer.',
            destructiva: true,
            onConfirm: () => onDelete(Number(c.id)),
          }),
        },
      ]} />
    ),
  },
]
