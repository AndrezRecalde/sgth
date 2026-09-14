import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { triajeService } from '../services/triajeService'
import { getApiErrorMessage } from '@/types/api'
import type { CrearTriajeData } from '../services/triajeService'
import { notificar } from '@/components/ui'

export function useTriajesPendientes() {
  return useQuery({
    queryKey: ['triaje', 'pendientes'],
    queryFn:  triajeService.pendientes,
    staleTime: 1000 * 15,
    refetchInterval: 1000 * 30,
  })
}

export function useUltimoTriaje(agendaId: number) {
  return useQuery({
    queryKey: ['triaje', 'ultimo', agendaId],
    queryFn:  () => triajeService.ultimoPorAgenda(agendaId),
    staleTime: 1000 * 60,
  })
}

/** Las tomas ya registradas del turno, para poder compararlas al rehacer. */
export function useHistorialTriaje(agendaId: number) {
  return useQuery({
    queryKey: ['triaje', 'historial', agendaId],
    queryFn:  () => triajeService.historial(agendaId),
    enabled:  !!agendaId,
  })
}

export function useRegistrarTriaje() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({
      agendaId, data,
    }: { agendaId: number; data: CrearTriajeData }) =>
      triajeService.registrar(agendaId, data),
    onSuccess: () => {
      notificar.exito(
        'Triaje registrado',
        'Los signos vitales fueron registrados correctamente.',
      )
      qc.invalidateQueries({ queryKey: ['triaje'] })
      qc.invalidateQueries({ queryKey: ['agenda'] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}
