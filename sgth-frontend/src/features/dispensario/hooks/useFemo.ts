import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { femoService } from '../services/femoService'
import type { CrearFemoData } from '../services/femoService'
import { notificar } from '@/components/ui'

export function useFemos(params?: Record<string, unknown>) {
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
      notificar.exito('FEMO registrada', 'La ficha fue registrada correctamente.')
      qc.invalidateQueries({ queryKey: ['femos'] })
    },
    onError: notificar.alFallar('No se pudo registrar la FEMO'),
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
      notificar.exito('FEMO actualizada', 'Los cambios fueron guardados.')
      qc.invalidateQueries({ queryKey: ['femos'] })
      qc.invalidateQueries({ queryKey: ['femo', id] })
    },
    onError: notificar.alFallar('No se pudo actualizar la FEMO'),
  })
}
