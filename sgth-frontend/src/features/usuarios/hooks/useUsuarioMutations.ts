import { useMutation, useQueryClient } from '@tanstack/react-query'
import { usuarioService } from '../services/usuarioService'
import type { UsuarioFormData, UsuarioUpdateData } from '@/types/api'
import { notificar } from '@/components/ui'

export function useUsuarioMutations() {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['usuarios'] })

  const crear = useMutation({
    mutationFn: (data: UsuarioFormData) =>
      usuarioService.crear(data),
    onSuccess: () => {
      notificar.exito('Usuario creado', 'El usuario fue creado correctamente.')
      invalidar()
      // Ese servidor ya no está disponible para vincular a otro usuario.
      qc.invalidateQueries({ queryKey: ['servidores-sin-usuario'] })
    },
    onError: notificar.alFallar('No se pudo crear el usuario'),
  })

  const actualizar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: UsuarioUpdateData }) =>
      usuarioService.actualizar(id, data),
    onSuccess: () => {
      notificar.exito('Usuario actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar el usuario'),
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
      notificar.exito(`Usuario ${estado}`, `El usuario fue ${estado} correctamente.`)
    },
    onError: (error, _id, context) => {
      // Revertir si falla
      if (context?.snapshot) {
        context.snapshot.forEach(([queryKey, data]) => {
          qc.setQueryData(queryKey, data)
        })
      }
      notificar.alFallar('No se pudo cambiar el estado del usuario')(error)
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
      notificar.exito(
        'Contraseña restablecida',
        'La contraseña volvió a ser la cédula del servidor y sus sesiones se cerraron.',
      )
      // primer_login vuelve a true y la tabla lo muestra.
      invalidar()
    },
    onError: notificar.alFallar('No se pudo restablecer la contraseña'),
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
      notificar.exito(
        'Permisos actualizados',
        'Los permisos fueron sincronizados correctamente.',
      )
      invalidar()
      // Sin esto el drawer volvía a abrirse con los permisos previos: la query
      // tiene staleTime de 5 min y solo se invalidaba la lista de usuarios.
      qc.invalidateQueries({ queryKey: ['permisos-usuario', id] })
    },
    onError: notificar.alFallar('No se pudieron actualizar los permisos'),
  })

  const desvincularServidor = useMutation({
    mutationFn: (id: number) =>
      usuarioService.desvincularServidor(id),
    onSuccess: () => {
      notificar.exito(
        'Servidor desvinculado',
        'El usuario quedó inactivo y sin expediente asociado.',
      )
      invalidar()
    },
    onError: notificar.alFallar('No se pudo desvincular al servidor'),
  })

  const asignarServidor = useMutation({
    mutationFn: ({ id, servidorId }: { id: number; servidorId: number }) =>
      usuarioService.asignarServidor(id, servidorId),
    onSuccess: () => {
      notificar.exito(
        'Servidor asignado',
        'La ficha fue vinculada y el usuario quedó activo.',
      )
      invalidar()
      qc.invalidateQueries({ queryKey: ['servidores-sin-usuario'] })
    },
    onError: notificar.alFallar('No se pudo asignar el servidor'),
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
