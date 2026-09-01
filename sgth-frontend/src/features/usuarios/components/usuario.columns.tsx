import { Text, Badge, Switch, Tooltip, Group, Stack } from '@mantine/core'
import {
  IconEdit, IconKey, IconShieldCheck, IconUserOff, IconUserCheck,
} from '@tabler/icons-react'
import { TableActions } from '@/components/ui/TableActions'
import type { DataTableColumn } from 'mantine-datatable'
import type { Usuario } from '@/types/api'
import { colorRol, etiquetaRol } from '../constants/roles'

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
    render: ({ usuario_ti, email, primer_login }) => (
      <Stack gap={2}>
        <Group gap={6} wrap="nowrap">
          <Text size="sm" ff="monospace">{usuario_ti ?? '—'}</Text>
          {primer_login && (
            <Tooltip
              label="Aún no ha cambiado la contraseña inicial (su cédula)"
              withArrow
            >
              <Badge size="xs" color="orange" variant="light">
                clave inicial
              </Badge>
            </Tooltip>
          )}
        </Group>
        <Text size="xs" c="dimmed">{email}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'roles',
    title:    'Rol(es)',
    render: ({ roles }) => (
      <Group gap={4} wrap="wrap">
        {(roles ?? []).map(r => (
          <Badge key={r} size="xs" variant="light" color={colorRol(r)}>
            {etiquetaRol(r)}
          </Badge>
        ))}
      </Group>
    ),
  },
  {
    accessor: 'activo',
    title:    'Estado',
    width:    90,
    render: (usuario) => {
      const sinServidor = !usuario.servidor_id
      return (
        <Tooltip
          label={
            sinServidor
              ? 'Vincule un servidor para poder activarlo'
              : usuario.activo ? 'Desactivar acceso' : 'Activar acceso'
          }
          withArrow
        >
          {/* Tooltip necesita un elemento que acepte ref incluso deshabilitado. */}
          <div style={{ display: 'inline-flex' }}>
            <Switch
              checked={!!usuario.activo}
              onChange={() => onToggleActivo(usuario)}
              disabled={sinServidor}
              color="emerald"
              size="sm"
              aria-label={usuario.activo ? 'Desactivar usuario' : 'Activar usuario'}
            />
          </div>
        </Tooltip>
      )
    },
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
          // El backend la restablece a la cédula del servidor: sin ficha
          // vinculada no hay a qué restablecerla.
          hidden:  !usuario.servidor_id,
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
