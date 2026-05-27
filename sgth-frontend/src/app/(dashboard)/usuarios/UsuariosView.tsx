'use client'

import { useState } from 'react'
import { Box, Button, Group } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconUsers, IconUserPlus } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { EmptyState } from '@/components/ui/EmptyState'
import { UsuarioToolbar } from '@/features/usuarios/components/UsuarioToolbar'
import { UsuarioTable } from '@/features/usuarios/components/UsuarioTable'
import { UsuarioDrawer } from '@/features/usuarios/components/UsuarioDrawer'
import { UsuarioModal } from '@/features/usuarios/components/UsuarioModal'
import { useUsuarios } from '@/features/usuarios/hooks/useUsuarios'
import { useUsuarioMutations } from '@/features/usuarios/hooks/useUsuarioMutations'
import type { Usuario } from '@/types/api'

export function UsuariosView() {
  const [page, setPage]     = useState(1)
  const [search, setSearch] = useState('')
  const [rol, setRol]       = useState<string | null>(null)

  // Drawer — crear nuevo usuario
  const [drawerOpened, { open: openDrawer, close: closeDrawer }] =
    useDisclosure(false)

  // Modal — editar usuario existente
  const [modalOpened, { open: openModal, close: closeModal }] =
    useDisclosure(false)
  const [editUsuario, setEditUsuario] = useState<Usuario | null>(null)

  const { data, isLoading } = useUsuarios({
    page,
    per_page: 15,
    search:   search || undefined,
    rol:      rol    || undefined,
  })

  const { toggleActivo, restablecerContrasena } = useUsuarioMutations()

  const usuarios = (data?.data ?? []) as Usuario[]

  const handleEdit = (u: Usuario) => {
    setEditUsuario(u)
    openModal()
  }

  const handleCloseModal = () => {
    setEditUsuario(null)
    closeModal()
  }

  const handleRestablecerPassword = (u: Usuario) => {
    const displayName = u.nombre_completo
      || u.servidor?.nombre
      || u.email
      || '(Sin nombre)'
    if (confirm(
      `¿Restablecer la contraseña de ${displayName}?\n` +
      `Se establecerá la cédula del servidor como nueva contraseña.`
    )) {
      restablecerContrasena.mutate(Number(u.id))
    }
  }

  return (
    <Box>
      <PageHeader
        title="Gestión de Usuarios"
        subtitle="Administración de accesos al sistema SGTH"
        icon={<IconUsers size={28} />}
      />

      <Group justify="flex-end" mb="md">
        <Button
          color="emerald"
          variant="light"
          leftSection={<IconUserPlus size={16} />}
          onClick={openDrawer}
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
              onClick={openDrawer}
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
          onEdit={handleEdit}
          onToggleActivo={(u) => toggleActivo.mutate(Number(u.id))}
          onRestablecerPassword={handleRestablecerPassword}
        />
      )}

      {/* Drawer para crear nuevo usuario */}
      <UsuarioDrawer
        opened={drawerOpened}
        onClose={closeDrawer}
      />

      {/* Modal para editar usuario existente */}
      <UsuarioModal
        opened={modalOpened}
        onClose={handleCloseModal}
        usuario={editUsuario}
      />
    </Box>
  )
}
