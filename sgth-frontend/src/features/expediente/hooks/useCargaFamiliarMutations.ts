import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { expedienteService } from '../services/expedienteService'
import type { ApiResponse } from '@/types/api'
import { notificar } from '@/components/ui'

export function useCargaFamiliarMutations(servidorId: number) {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['cargas-familiares', servidorId] })
  const onError = (e: AxiosError<ApiResponse>) =>
    notificar.error('Error', e.response?.data?.mensaje ?? 'Error inesperado')

  const crear = useMutation({
    mutationFn: (data: Parameters<typeof expedienteService.crearCargaFamiliar>[1]) =>
      expedienteService.crearCargaFamiliar(servidorId, data),
    onSuccess: () => {
      notificar.exito('Carga familiar registrada', 'La carga familiar fue registrada.')
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Parameters<typeof expedienteService.editarCargaFamiliar>[2] }) =>
      expedienteService.editarCargaFamiliar(servidorId, id, data),
    onSuccess: () => {
      notificar.exito('Carga familiar actualizada', 'La carga familiar fue actualizada.')
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      expedienteService.eliminarCargaFamiliar(servidorId, id),
    onSuccess: () => {
      notificar.exito('Eliminado', 'La carga familiar fue eliminada.')
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
      notificar.exito(
        `Carga familiar ${estado}`,
        `La carga familiar fue ${estado} correctamente.`,
      )
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
