'use client'

import { useState }           from 'react'
import { Button, Group } from '@mantine/core'
import { useDisclosure }      from '@mantine/hooks'
import { IconUsers, IconUserPlus } from '@tabler/icons-react'
import { PageHeader }         from '@/components/ui/PageHeader'
import { EmptyState }         from '@/components/ui/EmptyState'
import { UsuarioToolbar }     from '@/features/usuarios/components/UsuarioToolbar'
import { UsuarioTable }       from '@/features/usuarios/components/UsuarioTable'
import { UsuarioDrawer }      from '@/features/usuarios/components/UsuarioDrawer'
import { PermisosDrawer }     from '@/features/usuarios/components/PermisosDrawer'
import { AsignarServidorModal } from '@/features/usuarios/components/AsignarServidorModal'
import { useUsuarios }        from '@/features/usuarios/hooks/useUsuarios'
import { useUsuarioMutations } from '@/features/usuarios/hooks/useUsuarioMutations'
import type { Usuario }       from '@/types/api'
import { PageShell } from '@/components/ui'

export function UsuariosView() {
  const [page,   setPage]   = useState(1)
  const [search, setSearch] = useState('')
  const [rol,    setRol]    = useState<string | null>(null)

  const [usuarioSel,  setUsuarioSel]  = useState<Usuario | null>(null)
  const [permisosUsr, setPermisosUsr] = useState<Usuario | null>(null)
  const [asignarUsr,  setAsignarUsr]  = useState<Usuario | null>(null)

  const [drawerOpened,
    { open: openDrawer, close: closeDrawer }]   = useDisclosure(false)
  const [permisosOpened,
    { open: openPermisos, close: closePermisos }] = useDisclosure(false)
  const [asignarOpened,
    { open: openAsignar, close: closeAsignar }]   = useDisclosure(false)

  const { data, isLoading } = useUsuarios({
    page,
    per_page: 15,
    search:   search || undefined,
    rol:      rol    || undefined,
  })

  const { toggleActivo, restablecerContrasena, desvincularServidor } = useUsuarioMutations()

  const usuarios = (data?.data ?? []) as Usuario[]

  const handleNuevo = () => {
    setUsuarioSel(null)
    openDrawer()
  }

  const handleEditar = (u: Usuario) => {
    setUsuarioSel(u)
    openDrawer()
  }

  const handleCloseDrawer = () => {
    setUsuarioSel(null)
    closeDrawer()
  }

  const handlePermisos = (u: Usuario) => {
    setPermisosUsr(u)
    openPermisos()
  }

  const handleClosePermisos = () => {
    setPermisosUsr(null)
    closePermisos()
  }

  const handleRestablecerPassword = (u: Usuario) => {
    const nombre = u.nombre_completo
      || u.servidor?.nombre
      || u.email
      || '(Sin nombre)'
    if (confirm(
      `¿Restablecer la contraseña de ${nombre}?\n` +
      `Se establecerá la cédula del servidor como nueva contraseña.`
    )) {
      restablecerContrasena.mutate(Number(u.id))
    }
  }

  const handleDesvincular = (u: Usuario) => {
    const nombre = u.nombre_completo
      || u.servidor?.nombre
      || u.email
      || '(Sin nombre)'
    if (confirm(
      `¿Desvincular el servidor de ${nombre}?\n` +
      `El usuario conservará su acceso al sistema.`
    )) {
      desvincularServidor.mutate(Number(u.id))
    }
  }

  const handleAsignarServidor = (u: Usuario) => {
    setAsignarUsr(u)
    openAsignar()
  }

  return (
    <PageShell>
      <PageHeader
        title="Gestión de Usuarios"
        description="Administración de accesos al sistema SGTH"
      />

      <Group justify="flex-end">
        <Button
          color="emerald"
          variant="light"
          leftSection={<IconUserPlus size={16} />}
          onClick={handleNuevo}
        >
          Nuevo usuario
        </Button>
      </Group>

      <UsuarioToolbar
        onSearch={setSearch}
        onRolChange={setRol}
      />

      {!isLoading && usuarios.length === 0 ? (
        <EmptyState
          icon={IconUsers}
          title="No hay usuarios registrados"
          description="Comienza creando el primer usuario del sistema."
          action={
            <Button
              color="emerald"
              variant="light"
              leftSection={<IconUserPlus size={14} />}
              onClick={handleNuevo}
            >
              Nuevo usuario
            </Button>
          }
        />
      ) : (
        <UsuarioTable
          data={usuarios}
          isLoading={isLoading}
          total={data?.total ?? 0}
          page={page}
          onPageChange={setPage}
          onEdit={handleEditar}
          onToggleActivo={(u) => toggleActivo.mutate(Number(u.id))}
          onRestablecerPassword={handleRestablecerPassword}
          onPermisos={handlePermisos}
          onDesvincular={handleDesvincular}
          onAsignarServidor={handleAsignarServidor}
        />
      )}

      <UsuarioDrawer
        opened={drawerOpened}
        onClose={handleCloseDrawer}
        usuario={usuarioSel}
      />

      <PermisosDrawer
        opened={permisosOpened}
        onClose={handleClosePermisos}
        usuario={permisosUsr}
      />

      <AsignarServidorModal
        opened={asignarOpened}
        onClose={() => {
          setAsignarUsr(null)
          closeAsignar()
        }}
        usuario={asignarUsr}
      />
    </PageShell>
  )
}
