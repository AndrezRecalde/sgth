'use client'

import { useState } from 'react'
import { Box } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconUsers } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { UsuarioToolbar } from '@/features/usuarios/components/UsuarioToolbar'
import { UsuarioTable } from '@/features/usuarios/components/UsuarioTable'
import { UsuarioModal } from '@/features/usuarios/components/UsuarioModal'
import { useUsuarios } from '@/features/usuarios/hooks/useUsuarios'
import { useUsuarioMutations } from '@/features/usuarios/hooks/useUsuarioMutations'
import type { Usuario } from '@/types/api'

export function UsuariosView() {
  const [page, setPage]     = useState(1)
  const [search, setSearch] = useState('')
  const [rol, setRol]       = useState<string | null>(null)

  const [modalOpened, { open, close }] = useDisclosure(false)
  const [editUsuario, setEditUsuario]  = useState<Usuario | null>(null)

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
    open()
  }

  const handleNuevo = () => {
    setEditUsuario(null)
    open()
  }

  const handleClose = () => {
    setEditUsuario(null)
    close()
  }

  const handleRestablecerPassword = (u: Usuario) => {
    if (confirm(`¿Restablecer la contraseña de ${u.name}?\nSe establecerá la cédula del servidor como nueva contraseña.`)) {
      restablecerContrasena.mutate(Number(u.id))
    }
  }

  return (
    <Box>
      <PageHeader
        title="Gestión de Usuarios"
        subtitle="Administración de accesos al sistema"
        icon={<IconUsers size={28} />}
      />
      <UsuarioToolbar
        onSearch={setSearch}
        onRolChange={setRol}
        onNuevo={handleNuevo}
      />
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
      <UsuarioModal
        opened={modalOpened}
        onClose={handleClose}
        usuario={editUsuario}
      />
    </Box>
  )
}
