import { useMutation, useQueryClient } from '@tanstack/react-query'
import { subrogacionService } from '../services/subrogacionService'
import type { SubrogacionFormData } from '../schemas/subrogacion.schema'
import { notificar } from '@/components/ui'

export function useSubrogacionMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['subrogaciones-activas'] })
    qc.invalidateQueries({ queryKey: ['subrogaciones-vigentes'] })
  }

  const registrar = useMutation({
    mutationFn: (data: SubrogacionFormData) => subrogacionService.registrar(data),
    onSuccess: () => {
      notificar.exito('Subrogación registrada', 'La subrogación/encargo fue registrado correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar la subrogación'),
  })

  const finalizar = useMutation({
    mutationFn: (id: number) => subrogacionService.finalizar(id),
    onSuccess: () => {
      notificar.exito('Subrogación finalizada', 'La subrogación/encargo fue finalizado correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo finalizar la subrogación'),
  })

  const cancelar = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      subrogacionService.cancelar(id, motivo),
    onSuccess: () => {
      notificar.exito('Subrogación cancelada', 'La subrogación/encargo fue cancelado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo cancelar la subrogación'),
  })

  return { registrar, finalizar, cancelar }
}
