import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { puestoActividadService, type PuestoActividadDatos } from '../services/puestoActividadService'
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
    mutationFn: (data: PuestoActividadDatos) =>
      puestoActividadService.crear(puestoId, data),
    onSuccess: () => {
      notificar.exito('Actividad agregada', 'La actividad fue registrada correctamente.')
      qc.invalidateQueries({ queryKey: ['puesto-actividades', puestoId] })
    },
    onError: notificar.alFallarSalvoCampos('No se pudo agregar la actividad'),
  })
}

/** Cambiar el contenido de una actividad desde su formulario. */
export function useEditarActividad(puestoId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: PuestoActividadDatos }) =>
      puestoActividadService.actualizar(puestoId, id, data),
    onSuccess: () => {
      notificar.exito('Actividad actualizada', 'Los cambios de la actividad fueron guardados.')
      qc.invalidateQueries({ queryKey: ['puesto-actividades', puestoId] })
    },
    onError: notificar.alFallarSalvoCampos('No se pudo actualizar la actividad'),
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
    onError: notificar.alFallar('No se pudo eliminar la actividad'),
  })
}

/** Activar o desactivar una actividad: el interruptor ya muestra el resultado. */
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
    onError: notificar.alFallar('No se pudo actualizar la actividad'),
  })
}
