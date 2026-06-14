import { Text, Badge, Switch, Tooltip, Group, Stack } from '@mantine/core'
import { IconEdit, IconKey, IconShieldCheck, IconUserOff, IconUserCheck } from '@tabler/icons-react'
import { TableActions } from '@/components/ui/TableActions'
import type { DataTableColumn } from 'mantine-datatable'
import type { Usuario } from '@/types/api'

const ROL_COLORS: Record<string, string> = {
  'admin-ti':          'red',
  'admin-uath':        'violet',
  'asistente-uath':    'grape',
  'maxima-autoridad':  'dark',
  'director':          'blue',
  'jefe-unidad':       'cyan',
  'servidor':          'teal',
  'recepcion':         'orange',
  'trabajo-social':    'pink',
  'medico':            'green',
  'odontologo':        'lime',
  'enfermera':         'yellow',
  'admin-dispensario': 'indigo',
  'tecnico-dtic':      'gray',
  'auditor':           'brown',
}

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
  onPermisos:            (u: Usuario) => void
  onDesvincular:         (u: Usuario) => void
  onAsignarServidor:     (u: Usuario) => void
}

export const getUsuarioColumns = ({
  onEdit,
  onToggleActivo,
  onRestablecerPassword,
  onPermisos,
  onDesvincular,
  onAsignarServidor,
}: Handlers): DataTableColumn<Usuario>[] => [
  {
    accessor: 'servidor',
    title:    'Servidor',
    render: ({ nombre_completo, servidor, servidor_id }) => {
      if (!servidor_id) {
        return (
          <Badge color="red" variant="light" size="sm" leftSection={<IconUserOff size={12} />}>
            SIN SERVIDOR
          </Badge>
        )
      }

      return (
        <Stack gap={0}>
          <Text size="sm" fw={500}>
            {nombre_completo || servidor?.nombre || '—'}
          </Text>
          <Text size="xs" c="dimmed">
            CI: {servidor?.cedula ?? '—'}
          </Text>
        </Stack>
      )
    },
  },
  {
    accessor: 'usuario_ti',
    title:    'Usuario TI',
    render: ({ usuario_ti, email }) => (
      <Stack gap={0}>
        <Text size="sm" ff="monospace">{usuario_ti ?? '—'}</Text>
        <Text size="xs" c="dimmed">{email}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'roles',
    title:    'Rol(es)',
    render: ({ roles }) => (
      <Group gap={4} wrap="wrap">
        {(Array.isArray(roles) ? roles : []).map(r => (
          <Badge
            key={r}
            size="xs"
            variant="light"
            color={ROL_COLORS[r] ?? 'gray'}
          >
            {ROL_LABELS[r] ?? r}
          </Badge>
        ))}
      </Group>
    ),
  },
  {
    accessor: 'activo',
    title:    'Estado',
    width:    80,
    render: (usuario) => (
      <Tooltip
        label={usuario.activo ? 'Desactivar' : 'Activar'}
        withArrow
      >
        <Switch
          checked={
            String(usuario.activo) === '1' ||
            String(usuario.activo) === 'true'
          }
          onChange={() => onToggleActivo(usuario)}
          color="emerald"
          size="sm"
        />
      </Tooltip>
    ),
  },
  {
    accessor: 'acciones',
    title:    '',
    width:    50,
    render: (usuario) => (
      <TableActions actions={[
        {
          label:   'Editar usuario',
          icon:    <IconEdit size={14} />,
          color:   'blue',
          onClick: () => onEdit(usuario),
        },
        {
          label:   'Asignar permisos',
          icon:    <IconShieldCheck size={14} />,
          color:   'violet',
          onClick: () => onPermisos(usuario),
        },
        {
          label:   'Restablecer contraseña',
          icon:    <IconKey size={14} />,
          color:   'orange',
          onClick: () => onRestablecerPassword(usuario),
        },
        {
          label:   'Asignar servidor',
          icon:    <IconUserCheck size={14} />,
          color:   'teal',
          onClick: () => onAsignarServidor(usuario),
          hidden:  !!usuario.servidor_id,
        },
        {
          label:   'Desvincular servidor',
          icon:    <IconUserOff size={14} />,
          color:   'orange',
          onClick: () => onDesvincular(usuario),
          hidden:  !usuario.servidor_id,
        },
      ]} />
    ),
  },
]
