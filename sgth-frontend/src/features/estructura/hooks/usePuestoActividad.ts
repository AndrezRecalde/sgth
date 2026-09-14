import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { puestoActividadService } from '../services/puestoActividadService'
import { getApiErrorMessage } from '@/types/api'
import { notificar } from '@/components/ui'

export function usePuestoActividades(puestoId: number | null) {
  return useQuery({
    queryKey: ['puesto-actividades', puestoId],
    queryFn:  () => puestoActividadService.listar(puestoId!),
    enabled:  !!puestoId,
    staleTime: 1000 * 60,
  })
}

export function useCrearActividad(puestoId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (descripcion: string) =>
      puestoActividadService.crear(puestoId, descripcion),
    onSuccess: () => {
      notificar.exito('Actividad agregada', 'La actividad fue registrada correctamente.')
      qc.invalidateQueries({ queryKey: ['puesto-actividades', puestoId] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useEliminarActividad(puestoId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (actividadId: number) =>
      puestoActividadService.eliminar(puestoId, actividadId),
    onSuccess: () => {
      notificar.exito('Actividad eliminada', 'La actividad fue removida.')
      qc.invalidateQueries({ queryKey: ['puesto-actividades', puestoId] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useActualizarActividad(puestoId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, data }: {
      id:   number
      data: Partial<{ descripcion: string; activo: boolean }>
    }) => puestoActividadService.actualizar(puestoId, id, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['puesto-actividades', puestoId] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}
