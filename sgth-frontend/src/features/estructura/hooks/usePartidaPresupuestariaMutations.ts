import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { partidaPresupuestariaService } from '../services/partidaPresupuestariaService'
import type { ApiResponse, PartidaPresupuestariaFormData } from '@/types/api'
import { notificar } from '@/components/ui'

export function usePartidaPresupuestariaMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['partidas-presupuestarias'] })
    // Los puestos embeben la partida en sus respuestas.
    qc.invalidateQueries({ queryKey: ['puestos'] })
  }

  const onError = (error: AxiosError<ApiResponse>) => {
    notificar.error('Error', error.response?.data?.mensaje ?? 'Error inesperado')
  }

  const crear = useMutation({
    mutationFn: (data: PartidaPresupuestariaFormData) =>
      partidaPresupuestariaService.crear(data),
    onSuccess: () => {
      notificar.exito(
        'Partida creada',
        'La partida presupuestaria fue registrada correctamente.',
      )
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<PartidaPresupuestariaFormData> }) =>
      partidaPresupuestariaService.actualizar(id, data),
    onSuccess: () => {
      notificar.exito('Partida actualizada', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => partidaPresupuestariaService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Partida eliminada', 'La partida presupuestaria fue eliminada.')
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
