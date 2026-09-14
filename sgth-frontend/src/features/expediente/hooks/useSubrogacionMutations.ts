import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { subrogacionService } from '../services/subrogacionService'
import type { SubrogacionFormData } from '../schemas/subrogacion.schema'
import type { ApiResponse } from '@/types/api'
import { notificar } from '@/components/ui'

export function useSubrogacionMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['subrogaciones-activas'] })
    qc.invalidateQueries({ queryKey: ['subrogaciones-vigentes'] })
  }

  const onError = (error: AxiosError<ApiResponse>) => {
    notificar.error('Error', error.response?.data?.mensaje ?? 'Error inesperado')
  }

  const registrar = useMutation({
    mutationFn: (data: SubrogacionFormData) => subrogacionService.registrar(data),
    onSuccess: () => {
      notificar.exito('Registrado', 'La subrogación/encargo fue registrado correctamente.')
      invalidar()
    },
    onError,
  })

  const finalizar = useMutation({
    mutationFn: (id: number) => subrogacionService.finalizar(id),
    onSuccess: () => {
      notificar.exito('Finalizado', 'La subrogación/encargo fue finalizado correctamente.')
      invalidar()
    },
    onError,
  })

  const cancelar = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      subrogacionService.cancelar(id, motivo),
    onSuccess: () => {
      notificar.exito('Cancelado', 'La subrogación/encargo fue cancelado.')
      invalidar()
    },
    onError,
  })

  return { registrar, finalizar, cancelar }
}
