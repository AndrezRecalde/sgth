import {
  Text, Badge, Group, ActionIcon, Switch, Tooltip,
} from '@mantine/core'
import {
  IconEdit, IconKey, IconUserCheck, IconUserX,
} from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import type { Usuario } from '@/types/api'

const ROL_LABELS: Record<string, string> = {
  'admin-ti':          'Admin TI',
  'admin-uath':        'Admin UATH',
  'asistente-uath':    'Asistente UATH',
  'maxima-autoridad':  'Máxima Autoridad',
  'director':          'Director',
  'jefe-unidad':       'Jefe de Unidad',
  'servidor':          'Servidor',
  'recepcion':         'Recepción',
  'trabajo-social':    'Trabajo Social',
  'medico':            'Médico',
  'odontologo':        'Odontólogo',
  'enfermera':         'Enfermera',
  'admin-dispensario': 'Admin Dispensario',
  'tecnico-dtic':      'Técnico DTIC',
  'auditor':           'Auditor',
}

type Handlers = {
  onEdit:                (u: Usuario) => void
  onToggleActivo:        (u: Usuario) => void
  onRestablecerPassword: (u: Usuario) => void
}

export const getUsuarioColumns = (
  { onEdit, onToggleActivo, onRestablecerPassword }: Handlers
): DataTableColumn<Usuario>[] => [
  {
    accessor: 'name',
    title: 'Nombre',
    render: ({ name, usuario_ti }) => (
      <div>
        <Text size="sm" fw={500}>{name}</Text>
        <Text size="xs" c="dimmed">{usuario_ti}</Text>
      </div>
    ),
  },
  {
    accessor: 'email',
    title: 'Correo',
    render: ({ email }) => (
      <Text size="sm">{email}</Text>
    ),
  },
  {
    accessor: 'roles',
    title: 'Roles',
    render: ({ roles }) => (
      <Group gap={4}>
        {((roles as unknown as string[]) || []).map(r => (
          <Badge key={r} size="xs" variant="light" color="blue">
            {ROL_LABELS[r] ?? r}
          </Badge>
        ))}
      </Group>
    ),
  },
  {
    accessor: 'activo',
    title: 'Estado',
    width: 90,
    render: (usuario) => (
      <Tooltip
        label={usuario.activo ? 'Desactivar usuario' : 'Activar usuario'}
        withArrow
      >
        <Switch
          checked={String(usuario.activo) === '1' || String(usuario.activo) === 'true'}
          onChange={() => onToggleActivo(usuario)}
          color="emerald"
          size="sm"
        />
      </Tooltip>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 90,
    render: (usuario) => (
      <Group gap={4} justify="center">
        <Tooltip label="Editar usuario" withArrow>
          <ActionIcon
            variant="subtle"
            color="blue"
            onClick={() => onEdit(usuario)}
            aria-label="Editar usuario"
          >
            <IconEdit size={16} />
          </ActionIcon>
        </Tooltip>
        <Tooltip label="Restablecer contraseña" withArrow>
          <ActionIcon
            variant="subtle"
            color="orange"
            onClick={() => onRestablecerPassword(usuario)}
            aria-label="Restablecer contraseña"
          >
            <IconKey size={16} />
          </ActionIcon>
        </Tooltip>
      </Group>
    ),
  },
]
