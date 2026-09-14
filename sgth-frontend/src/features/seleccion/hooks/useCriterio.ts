import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { criterioService } from '../services/criterioService'
import type {
  CrearCriterioData, CalificacionItem,
} from '../services/criterioService'
import { notificar } from '@/components/ui'

export function useCriterios(convocatoriaId: number | null) {
  return useQuery({
    queryKey: ['criterios', convocatoriaId],
    queryFn:  () => criterioService.listar(convocatoriaId!),
    enabled:  !!convocatoriaId,
    staleTime: 1000 * 60,
  })
}

export function useCrearCriterio(convocatoriaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: CrearCriterioData) =>
      criterioService.crear(convocatoriaId, data),
    onSuccess: () => {
      notificar.exito('Criterio agregado', 'El criterio fue registrado correctamente.')
      qc.invalidateQueries({ queryKey: ['criterios', convocatoriaId] })
    },
    onError: notificar.alFallar('No se pudo agregar el criterio'),
  })
}

export function useEliminarCriterio(convocatoriaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (criterioId: number) =>
      criterioService.eliminar(convocatoriaId, criterioId),
    onSuccess: () => {
      notificar.exito('Criterio eliminado', 'El criterio fue removido.')
      qc.invalidateQueries({ queryKey: ['criterios', convocatoriaId] })
    },
    onError: notificar.alFallar('No se pudo eliminar el criterio'),
  })
}

export function useCalificaciones(
  convocatoriaId: number | null,
  postulanteId:   number | null
) {
  return useQuery({
    queryKey: ['calificaciones', convocatoriaId, postulanteId],
    queryFn:  () => criterioService.obtenerCalificaciones(
      convocatoriaId!, postulanteId!
    ),
    enabled:  !!convocatoriaId && !!postulanteId,
    staleTime: 1000 * 30,
  })
}

export function useGuardarCalificaciones(
  convocatoriaId: number,
  postulanteId:   number
) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (calificaciones: CalificacionItem[]) =>
      criterioService.guardarCalificaciones(
        convocatoriaId, postulanteId, calificaciones
      ),
    onSuccess: () => {
      notificar.exito(
        'Calificación guardada',
        'Los puntajes fueron registrados correctamente.',
      )
      qc.invalidateQueries({
        queryKey: ['calificaciones', convocatoriaId, postulanteId],
      })
      qc.invalidateQueries({
        queryKey: ['postulantes', convocatoriaId],
      })
      // Los mismos postulantes se listan en Reclutamiento Express bajo otra
      // clave; sin esto la tabla y los conteos del contenedor quedan viejos.
      qc.invalidateQueries({ queryKey: ['express-aspirantes'] })
      qc.invalidateQueries({ queryKey: ['express-resumen'] })
    },
    onError: notificar.alFallar('No se pudo guardar la calificación'),
  })
}
