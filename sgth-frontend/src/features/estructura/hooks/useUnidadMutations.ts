import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { estructuraService } from '../services/estructuraService'
import type { UnidadFormData } from '../schemas/unidad.schema'
import type { ApiResponse } from '@/types/api'
import { notificar } from '@/components/ui'

export function useUnidadMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['organigrama'] })
    qc.invalidateQueries({ queryKey: ['unidades'] })
  }

  const onError = (error: AxiosError<ApiResponse>) => {
    notificar.error('Error', error.response?.data?.mensaje ?? 'Error inesperado')
  }

  const crear = useMutation({
    mutationFn: (data: UnidadFormData) =>
      estructuraService.crearUnidad(data),
    onSuccess: () => {
      notificar.exito('Unidad creada', 'La unidad administrativa fue registrada.')
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: UnidadFormData }) =>
      estructuraService.editarUnidad(id, data),
    onSuccess: () => {
      notificar.exito('Unidad actualizada', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => estructuraService.eliminarUnidad(id),
    onSuccess: () => {
      notificar.exito('Unidad eliminada', 'La unidad fue eliminada correctamente.')
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
