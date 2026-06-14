import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { usuarioService } from '../services/usuarioService'
import type {
  ApiResponse,
  UsuarioFormData,
  UsuarioUpdateData,
} from '@/types/api'

export function useUsuarioMutations() {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['usuarios'] })

  const onError = (error: AxiosError<ApiResponse>) => {
    notifications.show({
      title: 'Error',
      message: error.response?.data?.mensaje ?? 'Error inesperado',
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })
  }

  const crear = useMutation({
    mutationFn: (data: UsuarioFormData) =>
      usuarioService.crear(data),
    onSuccess: () => {
      notifications.show({
        title: 'Usuario creado',
        message: 'El usuario fue creado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const actualizar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: UsuarioUpdateData }) =>
      usuarioService.actualizar(id, data),
    onSuccess: () => {
      notifications.show({
        title: 'Usuario actualizado',
        message: 'Los datos fueron actualizados.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const toggleActivo = useMutation({
    mutationFn: (id: number) => usuarioService.toggleActivo(id),
    onSuccess: (data) => {
      const estado = data?.activo ? 'activado' : 'desactivado'
      notifications.show({
        title: `Usuario ${estado}`,
        message: `El usuario fue ${estado} correctamente.`,
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const restablecerContrasena = useMutation({
    mutationFn: (id: number) =>
      usuarioService.restablecerContrasena(id),
    onSuccess: () => {
      notifications.show({
        title: 'Contraseña restablecida',
        message: 'La contraseña fue restablecida a la cédula del servidor.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
    },
    onError,
  })

  const sincronizarPermisos = useMutation({
    mutationFn: ({
      id,
      permisos,
    }: {
      id:       number
      permisos: string[]
    }) => usuarioService.sincronizarPermisos(id, permisos),
    onSuccess: () => {
      notifications.show({
        title:   'Permisos actualizados',
        message: 'Los permisos fueron sincronizados correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return {
    crear,
    actualizar,
    toggleActivo,
    restablecerContrasena,
    sincronizarPermisos,
  }
}
