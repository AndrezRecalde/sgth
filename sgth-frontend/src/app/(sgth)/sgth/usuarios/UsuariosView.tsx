'use client'

import { useState }           from 'react'
import { Button, Group } from '@mantine/core'
import { useDebouncedValue, useDisclosure } from '@mantine/hooks'
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
import { PageShell , confirmar } from '@/components/ui'

// El texto escrito entraba directo en la clave de consulta, así que cada tecla
// pedía un listado paginado entero y solo importaba el último.
const RETARDO_BUSQUEDA_MS = 300

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

  const [searchConRetardo] = useDebouncedValue(search, RETARDO_BUSQUEDA_MS)

  // Cambiar un filtro sin volver a la primera página consultaba esa misma
  // página del resultado ya filtrado —casi siempre vacía—, así que la tabla
  // salía en blanco aunque hubiera coincidencias.
  const buscar = (v: string) => {
    setSearch(v)
    setPage(1)
  }

  const filtrarPorRol = (v: string | null) => {
    setRol(v)
    setPage(1)
  }

  const { data, isLoading } = useUsuarios({
    page,
    per_page: 15,
    search:   searchConRetardo || undefined,
    rol:      rol              || undefined,
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

  const nombreDe = (u: Usuario) =>
    u.nombre_completo
      || u.servidor?.nombre
      || u.email
      || '(Sin nombre)'

  const handleRestablecerPassword = (u: Usuario) => confirmar({
    title:   'Restablecer contraseña',
    message: (
      <>
        Se restablecerá la contraseña de <b>{nombreDe(u)}</b>. Quedará como
        contraseña la cédula del servidor.
      </>
    ),
    destructiva: true,
    confirmLabel: 'Restablecer',
    onConfirm: () => restablecerContrasena.mutate(Number(u.id)),
  })

  const handleDesvincular = (u: Usuario) => confirmar({
    title:   'Desvincular servidor',
    message: (
      <>
        Se desvinculará el servidor de <b>{nombreDe(u)}</b>. El usuario
        conservará su acceso al sistema.
      </>
    ),
    destructiva: true,
    confirmLabel: 'Desvincular',
    onConfirm: () => desvincularServidor.mutate(Number(u.id)),
  })

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
        onSearch={buscar}
        onRolChange={filtrarPorRol}
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
