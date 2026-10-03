import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { femoService } from '../services/femoService'
import type { CrearFemoData, FiltrosFemo } from '../services/femoService'
import { notificar } from '@/components/ui'

export function useFemos(params?: FiltrosFemo) {
  return useQuery({
    queryKey: ['femos', params],
    queryFn:  () => femoService.listar(params),
    staleTime: 1000 * 60,
  })
}

export function useFemoDetalle(id: number | null) {
  return useQuery({
    queryKey: ['femo', id],
    queryFn:  () => femoService.obtener(id!),
    enabled:  !!id,
    staleTime: 1000 * 60,
  })
}

export function useCrearFemo() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearFemoData) => femoService.crear(data),
    onSuccess: () => {
      notificar.exito('Ficha guardada', 'Puede seguir editándola hasta emitir el dictamen.')
      qc.invalidateQueries({ queryKey: ['femos'] })
      // La solicitud gana su `ficha_femo_id`: «Continuar FEMO» la retoma.
      qc.invalidateQueries({ queryKey: ['solicitudes-certificacion'] })
    },
    onError: notificar.alFallar('No se pudo guardar la ficha'),
  })
}

export function useActualizarFemo() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: {
      id:   number
      data: Partial<CrearFemoData>
    }) => femoService.actualizar(id, data),
    onSuccess: (_, { id }) => {
      notificar.exito('Ficha guardada', 'Los cambios fueron guardados.')
      qc.invalidateQueries({ queryKey: ['femos'] })
      qc.invalidateQueries({ queryKey: ['femo', id] })
    },
    onError: notificar.alFallar('No se pudo guardar la ficha'),
  })
}
