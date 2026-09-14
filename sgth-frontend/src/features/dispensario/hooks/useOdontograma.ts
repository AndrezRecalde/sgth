import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { odontogramaService } from '../services/odontogramaService'
import { getApiErrorMessage } from '@/types/api'
import type {
  RegistrarProcedimientoData, AnularProcedimientoData,
} from '../services/odontogramaService'
import { notificar } from '@/components/ui'

export function useOdontograma(historiaClinicaId: number | null) {
  return useQuery({
    queryKey: ['odontograma', historiaClinicaId],
    queryFn:  () => odontogramaService.obtenerPorHistoriaClinica(historiaClinicaId!),
    enabled:  !!historiaClinicaId,
    staleTime: 1000 * 30,
  })
}

export function useRegistrarProcedimiento(historiaClinicaId: number | null) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: RegistrarProcedimientoData) =>
      odontogramaService.registrarProcedimiento(data),
    onSuccess: () => {
      notificar.exito(
        'Procedimiento registrado',
        'El odontograma fue actualizado correctamente.',
      )
      qc.invalidateQueries({ queryKey: ['odontograma', historiaClinicaId] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useAnularProcedimiento(historiaClinicaId: number | null) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: AnularProcedimientoData }) =>
      odontogramaService.anularProcedimiento(id, data),
    onSuccess: () => {
      notificar.exito(
        'Procedimiento anulado',
        'El registro quedó anulado en el historial y la pieza se actualizó.',
      )
      qc.invalidateQueries({ queryKey: ['odontograma', historiaClinicaId] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useHistorialPieza(piezaId: number | null) {
  return useQuery({
    queryKey: ['odontograma-pieza-historial', piezaId],
    queryFn:  () => odontogramaService.historialPieza(piezaId!),
    enabled:  !!piezaId,
    staleTime: 1000 * 30,
  })
}
