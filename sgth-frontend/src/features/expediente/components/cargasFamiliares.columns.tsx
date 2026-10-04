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
    accessor: 'genero',
    title: 'Sexo',
    width: 110,
    // Los registrados antes de existir el campo lo dicen, para completarlo.
    render: (c) => c.genero
      ? <Text size="sm">{c.genero === 'femenino' ? 'Femenino' : 'Masculino'}</Text>
      : <StatusBadge size="xs" tone="warning">Sin registrar</StatusBadge>,
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
    width: 190,
    // La marca la deriva el backend de los registros. Un familiar marcado a
    // mano antes de eso, sin registro, se avisa para que se complete.
    render: (c) => {
      const condiciones = [
        { marca: c.persona_con_discapacidad, detalle: c.discapacidades?.length ?? 0, texto: 'Discapacidad' },
        { marca: c.posee_enfermedad_catastrofica, detalle: c.enfermedades_catastroficas?.length ?? 0, texto: 'Enf. catastrófica' },
      ].filter((x) => x.marca || x.detalle > 0)

      if (condiciones.length === 0) return <Text size="sm" c="dimmed">—</Text>

      return (
        <Group gap="xs">
          {condiciones.map(({ texto, detalle }) => detalle > 0
            ? <StatusBadge key={texto} size="xs" variant="dot">{texto}</StatusBadge>
            : <StatusBadge key={texto} size="xs" tone="warning">{`${texto} · sin detalle`}</StatusBadge>)}
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
