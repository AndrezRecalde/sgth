'use client'

import { useState } from 'react'
import { Button, Group } from '@mantine/core'
import { useDebouncedValue, useDisclosure } from '@mantine/hooks'
import { IconUsers, IconUserPlus, IconSearchOff } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { EmptyState } from '@/components/ui/EmptyState'
import { UsuarioToolbar } from '@/features/usuarios/components/UsuarioToolbar'
import { UsuarioTable } from '@/features/usuarios/components/UsuarioTable'
import { UsuarioDrawer } from '@/features/usuarios/components/UsuarioDrawer'
import { PermisosDrawer } from '@/features/usuarios/components/PermisosDrawer'
import { AsignarServidorModal } from '@/features/usuarios/components/AsignarServidorModal'
import { useUsuarios } from '@/features/usuarios/hooks/useUsuarios'
import { useUsuarioMutations } from '@/features/usuarios/hooks/useUsuarioMutations'
import type { Usuario } from '@/types/api'
import { PageShell, confirmar } from '@/components/ui'

// El texto escrito entraba directo en la clave de consulta, así que cada tecla
// pedía un listado paginado entero y solo importaba el último.
const RETARDO_BUSQUEDA_MS = 300

export function UsuariosView() {
  const [page,   setPage]   = useState(1)
  const [search, setSearch] = useState('')
  const [rol,    setRol]    = useState<string | null>(null)
  const [estado, setEstado] = useState<string | null>(null)

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

  const filtrarPorEstado = (v: string | null) => {
    setEstado(v)
    setPage(1)
  }

  const { data, isLoading } = useUsuarios({
    page,
    per_page: 15,
    search:   searchConRetardo || undefined,
    rol:      rol              || undefined,
    activo:   estado === null ? undefined : estado === 'true',
  })

  const { toggleActivo, restablecerContrasena, desvincularServidor } =
    useUsuarioMutations()

  const usuarios = (data?.data ?? []) as Usuario[]

  // Se mira el término ya retardado, que es el que se consultó: con el texto en
  // crudo el mensaje cambiaría antes de que llegue el resultado.
  const hayFiltros =
    searchConRetardo !== '' || rol !== null || estado !== null

  const handleNuevo = () => {
    setUsuarioSel(null)
    openDrawer()
  }

  const handleEditar = (u: Usuario) => {
    setUsuarioSel(u)
    openDrawer()
  }

  // No se limpia `usuarioSel` al cerrar: el drawer elige entre alta y edición
  // según ese valor, así que ponerlo a null aquí cambiaría el contenido a mitad
  // de la animación de salida. `handleNuevo` ya lo limpia antes de abrir.
  const handleCloseDrawer = closeDrawer

  const handlePermisos = (u: Usuario) => {
    setPermisosUsr(u)
    openPermisos()
  }

  // Igual que en el drawer de usuario: no se limpia al cerrar. Al quedarse sin
  // `usuario`, el panel recalculaba los permisos heredados con cero roles y el
  // contador del pie saltaba de 12 a 19 a la vista, durante la animación de
  // salida. `handlePermisos` ya asigna el usuario antes de abrir.
  const handleClosePermisos = closePermisos

  const nombreDe = (u: Usuario) =>
    u.nombre_completo || u.servidor?.nombre || u.email || '(Sin nombre)'

  const handleRestablecerPassword = (u: Usuario) => confirmar({
    title:   'Restablecer contraseña',
    message: (
      <>
        La contraseña de <b>{nombreDe(u)}</b> volverá a ser su cédula
        ({u.servidor?.cedula ?? '—'}) y se cerrarán todas sus sesiones
        abiertas. Deberá cambiarla al volver a entrar.
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
        Se desvinculará la ficha de <b>{nombreDe(u)}</b>. El usuario quedará
        <b> inactivo</b> y perderá el acceso hasta que se le asigne otro
        servidor.
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

  const limpiarFiltros = () => {
    setSearch('')
    setRol(null)
    setEstado(null)
    setPage(1)
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
        search={search}
        rol={rol}
        estado={estado}
        onSearch={buscar}
        onRolChange={filtrarPorRol}
        onEstadoChange={filtrarPorEstado}
      />

      {!isLoading && usuarios.length === 0 ? (
        hayFiltros ? (
          <EmptyState
            icon={IconSearchOff}
            title="Ningún usuario coincide con la búsqueda"
            description="Prueba con otros términos o quita los filtros aplicados."
            action={
              <Button variant="default" onClick={limpiarFiltros}>
                Limpiar filtros
              </Button>
            }
          />
        ) : (
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
        )
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
        onClose={closeAsignar}
        usuario={asignarUsr}
      />
    </PageShell>
  )
}
