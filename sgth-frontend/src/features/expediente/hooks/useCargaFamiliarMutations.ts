import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { expedienteService } from '../services/expedienteService'
import type { ApiResponse } from '@/types/api'

export function useCargaFamiliarMutations(servidorId: number) {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['cargas-familiares', servidorId] })
  const onError = (e: AxiosError<ApiResponse>) =>
    notifications.show({
      title: 'Error', color: 'red',
      message: e.response?.data?.mensaje ?? 'Error inesperado',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const crear = useMutation({
    mutationFn: (data: Parameters<typeof expedienteService.crearCargaFamiliar>[1]) =>
      expedienteService.crearCargaFamiliar(servidorId, data),
    onSuccess: () => {
      notifications.show({
        title: 'Carga familiar registrada', color: 'emerald',
        message: 'La carga familiar fue registrada.',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Parameters<typeof expedienteService.editarCargaFamiliar>[2] }) =>
      expedienteService.editarCargaFamiliar(servidorId, id, data),
    onSuccess: () => {
      notifications.show({
        title: 'Carga familiar actualizada', color: 'emerald',
        message: 'La carga familiar fue actualizada.',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      expedienteService.eliminarCargaFamiliar(servidorId, id),
    onSuccess: () => {
      notifications.show({
        title: 'Eliminado', color: 'emerald',
        message: 'La carga familiar fue eliminada.',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const toggleEstado = useMutation({
    mutationFn: (id: number) =>
      expedienteService.toggleEstadoCarga(servidorId, id),
    onMutate: async (id: number) => {
      await qc.cancelQueries({
        queryKey: ['cargas-familiares', servidorId],
      })

      const snapshot = qc.getQueriesData({
        queryKey: ['cargas-familiares', servidorId],
      })

      qc.setQueriesData(
        { queryKey: ['cargas-familiares', servidorId] },
        (old: unknown) => {
          if (!Array.isArray(old)) return old
          return old.map((c: { id: number; estado: boolean }) =>
            Number(c.id) === id
              ? { ...c, estado: !c.estado }
              : c
          )
        }
      )

      return { snapshot }
    },
    onSuccess: (data) => {
      const estado = data?.estado ? 'activada' : 'desactivada'
      notifications.show({
        title:   `Carga familiar ${estado}`,
        message: `La carga familiar fue ${estado} correctamente.`,
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
    },
    onError: (error, _id, context) => {
      if (context?.snapshot) {
        context.snapshot.forEach(([queryKey, data]) => {
          qc.setQueryData(queryKey, data)
        })
      }
      onError(error as AxiosError<ApiResponse>)
    },
    onSettled: () => {
      invalidar()
    },
  })

  return { crear, editar, eliminar, toggleEstado }
}
