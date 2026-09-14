import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { plantillaService } from '../services/plantillaService'
import { getApiErrorMessage } from '@/types/api'
import { notificar } from '@/components/ui'

export function usePlantillas() {
  return useQuery({
    queryKey: ['plantillas'],
    queryFn:  () => plantillaService.listar(),
    staleTime: 1000 * 60 * 5,
  })
}

export function usePlantillaDetalle(id: number | null) {
  return useQuery({
    queryKey: ['plantilla', id],
    queryFn:  () => plantillaService.obtener(id!),
    enabled:  !!id,
    staleTime: 1000 * 60,
  })
}

export function useCrearPlantilla() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: plantillaService.crear,
    onSuccess: () => {
      notificar.exito('Plantilla creada', 'La plantilla fue registrada correctamente.')
      qc.invalidateQueries({ queryKey: ['plantillas'] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useEliminarPlantilla() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => plantillaService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Plantilla eliminada', 'La plantilla fue removida.')
      qc.invalidateQueries({ queryKey: ['plantillas'] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useAgregarCriterioPlantilla(plantillaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: Parameters<typeof plantillaService.agregarCriterio>[1]) =>
      plantillaService.agregarCriterio(plantillaId, data),
    onSuccess: () => {
      notificar.exito('Criterio agregado', 'El criterio fue agregado a la plantilla.')
      qc.invalidateQueries({ queryKey: ['plantilla', plantillaId] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useEliminarCriterioPlantilla(plantillaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (criterioId: number) =>
      plantillaService.eliminarCriterio(plantillaId, criterioId),
    onSuccess: () => {
      notificar.exito('Criterio eliminado', 'El criterio fue removido de la plantilla.')
      qc.invalidateQueries({ queryKey: ['plantilla', plantillaId] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

// convocatoriaId viaja en las variables de mutate(), no como argumento del
// hook: en el formulario de creación (Reclutamiento Express) el id recién
// se conoce dentro del onSuccess de crear(), así que un valor fijado al
// instanciar el hook quedaría en un closure obsoleto. El mismo hook sirve
// para el flujo formal (SeleccionarPlantillaModal, id ya conocido de
// antemano) y para el encadenamiento de creación.
export function useAplicarPlantilla() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ plantillaId, convocatoriaId }: {
      plantillaId:    number
      convocatoriaId: number
    }) => plantillaService.aplicarAConvocatoria(plantillaId, convocatoriaId),
    onSuccess: (_, { convocatoriaId }) => {
      notificar.exito(
        'Plantilla aplicada',
        'Los criterios fueron copiados a la convocatoria.',
      )
      qc.invalidateQueries({ queryKey: ['criterios', convocatoriaId] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}
