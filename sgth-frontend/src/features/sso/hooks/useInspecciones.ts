import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { inspeccionesService } from '../services/inspeccionesService'
import type { InspeccionPayload } from '../services/tipos'
import { clavesSso } from '../constants/claves'
import { notificar } from '@/components/ui'

interface Params {
  page?: number
  unidad_administrativa_id?: number
  estado?: boolean
}

export function useInspecciones(params?: Params) {
  return useQuery({
    queryKey: clavesSso.inspecciones.lista(params),
    queryFn: () => inspeccionesService.listar(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useInspeccionMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: clavesSso.inspecciones.todas })
    // «Inspecciones realizadas» es uno de los cuatro índices proactivos del
    // período, y el tablero lo repite: registrar una sin invalidarlos los
    // dejaría en la cifra anterior.
    qc.invalidateQueries({ queryKey: clavesSso.indicadores.todos })
    qc.invalidateQueries({ queryKey: clavesSso.tablero.todo })
  }

  const crear = useMutation({
    mutationFn: (data: InspeccionPayload) => inspeccionesService.crear(data),
    onSuccess: () => {
      notificar.exito('Inspección registrada', 'La inspección fue registrada correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar la inspección'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: InspeccionPayload } ) =>
      inspeccionesService.actualizar(id, data),
    onSuccess: () => {
      notificar.exito('Inspección actualizada', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar la inspección'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => inspeccionesService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Inspección eliminada', 'El registro fue eliminado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la inspección'),
  })

  return { crear, editar, eliminar }
}
