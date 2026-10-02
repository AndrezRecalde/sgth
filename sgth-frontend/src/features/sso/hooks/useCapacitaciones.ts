import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { capacitacionesService } from '../services/capacitacionesService'
import type { CapacitacionPayload } from '../services/tipos'
import { clavesSso } from '../constants/claves'
import { notificar } from '@/components/ui'

interface Params {
  page?: number
  estado?: boolean
}

export function useCapacitaciones(params?: Params) {
  return useQuery({
    queryKey: clavesSso.capacitaciones.lista(params),
    queryFn: () => capacitacionesService.listar(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useCapacitacionMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: clavesSso.capacitaciones.todas })
    // De aquí salen dos de los cuatro índices proactivos —las capacitaciones
    // realizadas y el total de horas—, y el tablero repite el primero.
    qc.invalidateQueries({ queryKey: clavesSso.indicadores.todos })
    qc.invalidateQueries({ queryKey: clavesSso.tablero.todo })
  }

  const crear = useMutation({
    mutationFn: (data: CapacitacionPayload) => capacitacionesService.crear(data),
    onSuccess: () => {
      notificar.exito('Capacitación registrada', 'La capacitación fue registrada correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar la capacitación'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: CapacitacionPayload }) =>
      capacitacionesService.actualizar(id, data),
    onSuccess: () => {
      notificar.exito('Capacitación actualizada', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar la capacitación'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => capacitacionesService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Capacitación eliminada', 'El registro fue eliminado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la capacitación'),
  })

  return { crear, editar, eliminar }
}
