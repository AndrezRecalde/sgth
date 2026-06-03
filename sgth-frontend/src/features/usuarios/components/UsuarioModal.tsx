'use client'

import { Modal, Button, Group } from '@mantine/core'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useUsuarioMutations } from '../hooks/useUsuarioMutations'
import { UsuarioForm } from './UsuarioForm'
import type { UsuarioFormData } from '../schemas/usuario.schema'
import type { Usuario } from '@/types/api'

interface Props {
  opened:  boolean
  onClose: () => void
  usuario?: Usuario | null
}

export function UsuarioModal({ opened, onClose, usuario }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const { crear, actualizar } = useUsuarioMutations()
  const isEditing = !!usuario

  const handleSubmit = (values: UsuarioFormData) => {
    const mutation = isEditing
      ? actualizar.mutateAsync({
          id: Number(usuario!.id),
          data: {
            email:    values.email,
            roles:    values.roles,
          },
        })
      : crear.mutateAsync(values)

    mutation.then(onClose).catch(() => {})
  }

  const isPending = crear.isPending || actualizar.isPending

  const initialValues = usuario ? {
    email:       usuario.email ?? '',
    roles:       Array.isArray(usuario.roles) ? usuario.roles : [],
    servidor_id: usuario.servidor_id ?? null,
    cedula:      '',
  } : undefined

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={isEditing ? 'Editar usuario' : 'Nuevo usuario'}
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <UsuarioForm
        initialValues={initialValues}
        onSubmit={handleSubmit}
        isEditing={isEditing}
      />
      <Group justify="flex-end" mt="xl">
        <Button variant="default" onClick={onClose}>
          Cancelar
        </Button>
        <Button
          type="submit"
          form="usuario-form"
          loading={isPending}
          color="emerald"
        >
          {isEditing ? 'Actualizar' : 'Crear usuario'}
        </Button>
      </Group>
    </Modal>
  )
}
