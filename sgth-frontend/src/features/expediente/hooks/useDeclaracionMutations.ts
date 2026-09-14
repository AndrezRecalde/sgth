import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { expedienteService } from '../services/expedienteService'
import type { ApiResponse } from '@/types/api'
import { notificar } from '@/components/ui'

export function useDeclaracionMutations(servidorId: number) {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['declaraciones', servidorId] })
  const onError = (e: AxiosError<ApiResponse>) =>
    notificar.error('Error', e.response?.data?.mensaje ?? 'Error inesperado')

  const crear = useMutation({
    mutationFn: (data: Parameters<typeof expedienteService.crearDeclaracion>[1]) =>
      expedienteService.crearDeclaracion(servidorId, data),
    onSuccess: () => {
      notificar.exito('Declaración registrada', 'La declaración juramentada fue registrada.')
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      expedienteService.eliminarDeclaracion(servidorId, id),
    onSuccess: () => {
      notificar.exito('Eliminado', 'La declaración fue eliminada.')
      invalidar()
    },
    onError,
  })

  const exportar = () =>
    expedienteService.exportarDeclaraciones(servidorId)

  return { crear, eliminar, exportar }
}
