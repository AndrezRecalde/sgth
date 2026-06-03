import { Text, Badge, Switch, Tooltip } from '@mantine/core'
import { IconEdit, IconKey } from '@tabler/icons-react'
import { TableActions } from '@/components/ui/TableActions'
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
    accessor: 'nombre_completo',
    title: 'Nombre',
    render: ({ nombre_completo, usuario_ti, servidor }) => (
      <div>
        <Text size="sm" fw={500}>
          {nombre_completo || servidor?.nombre || '(Sin servidor vinculado)'}
        </Text>
        <Text size="xs" c="dimmed">{usuario_ti}</Text>
      </div>
    ),
  },
  {
    accessor: 'email',
    title: 'Correo institucional',
    render: ({ email }) => (
      <Text size="sm">{email}</Text>
    ),
  },
  {
    accessor: 'roles',
    title: 'Roles',
    render: ({ roles }) => (
      <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
        {(Array.isArray(roles) ? roles : []).map(r => (
          <Badge key={r} size="xs" variant="light" color="blue">
            {ROL_LABELS[r] ?? r}
          </Badge>
        ))}
      </div>
    ),
  },
  {
    accessor: 'activo',
    title: 'Estado',
    width: 90,
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
    title: '',
    width: 50,
    render: (usuario) => (
      <TableActions actions={[
        {
          label: 'Editar usuario',
          icon: <IconEdit size={14} />,
          color: 'blue',
          onClick: () => onEdit(usuario),
        },
        {
          label: 'Restablecer contraseña',
          icon: <IconKey size={14} />,
          color: 'orange',
          onClick: () => onRestablecerPassword(usuario),
        },
      ]} />
    ),
  },
]
