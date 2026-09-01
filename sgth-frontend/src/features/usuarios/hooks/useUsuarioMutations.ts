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
      // Ese servidor ya no está disponible para vincular a otro usuario.
      qc.invalidateQueries({ queryKey: ['servidores-sin-usuario'] })
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
    mutationFn: (id: number) =>
      usuarioService.toggleActivo(id),
    onMutate: async (id: number) => {
      // Cancelar queries en vuelo
      await qc.cancelQueries({ queryKey: ['usuarios'] })

      // Guardar snapshot anterior
      const snapshot = qc.getQueriesData({ queryKey: ['usuarios'] })

      // Actualizar optimísticamente
      qc.setQueriesData(
        { queryKey: ['usuarios'] },
        (old: unknown) => {
          if (!old || typeof old !== 'object') return old
          const data = old as {
            data?: { id: number; activo: unknown }[]
          }
          if (!Array.isArray(data.data)) return old
          return {
            ...data,
            data: data.data.map(u =>
              Number(u.id) === id
                ? { ...u, activo: !u.activo }
                : u
            ),
          }
        }
      )
      return { snapshot }
    },
    onSuccess: (data) => {
      const estado = data?.activo ? 'activado' : 'desactivado'
      notifications.show({
        title:   `Usuario ${estado}`,
        message: `El usuario fue ${estado} correctamente.`,
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
    },
    onError: (error, _id, context) => {
      // Revertir si falla
      if (context?.snapshot) {
        context.snapshot.forEach(([queryKey, data]) => {
          qc.setQueryData(queryKey, data)
        })
      }
      onError(error as AxiosError<ApiResponse>)
    },
    onSettled: () => {
      // Sincronizar con el servidor al terminar
      qc.invalidateQueries({ queryKey: ['usuarios'] })
    },
  })

  const restablecerContrasena = useMutation({
    mutationFn: (id: number) =>
      usuarioService.restablecerContrasena(id),
    onSuccess: () => {
      notifications.show({
        title: 'Contraseña restablecida',
        message: 'La contraseña volvió a ser la cédula del servidor y sus sesiones se cerraron.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      // primer_login vuelve a true y la tabla lo muestra.
      invalidar()
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
    onSuccess: (_data, { id }) => {
      notifications.show({
        title:   'Permisos actualizados',
        message: 'Los permisos fueron sincronizados correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
      // Sin esto el drawer volvía a abrirse con los permisos previos: la query
      // tiene staleTime de 5 min y solo se invalidaba la lista de usuarios.
      qc.invalidateQueries({ queryKey: ['permisos-usuario', id] })
    },
    onError,
  })

  const desvincularServidor = useMutation({
    mutationFn: (id: number) =>
      usuarioService.desvincularServidor(id),
    onSuccess: () => {
      notifications.show({
        title:   'Servidor desvinculado',
        message: 'El usuario quedó inactivo y sin expediente asociado.',
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const asignarServidor = useMutation({
    mutationFn: ({ id, servidorId }: { id: number; servidorId: number }) =>
      usuarioService.asignarServidor(id, servidorId),
    onSuccess: () => {
      notifications.show({
        title:   'Servidor asignado',
        message: 'La ficha fue vinculada y el usuario quedó activo.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
      qc.invalidateQueries({ queryKey: ['servidores-sin-usuario'] })
    },
    onError,
  })

  return {
    crear,
    actualizar,
    toggleActivo,
    restablecerContrasena,
    sincronizarPermisos,
    desvincularServidor,
    asignarServidor,
  }
}
