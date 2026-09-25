import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { equiposProteccionService } from '../services/eppService'
import type { EquipoProteccion } from '../services/tipos'
import { clavesSso } from '../constants/claves'
import { notificar } from '@/components/ui'

interface Params {
  page?: number
  tipo?: string
  estado?: boolean
}

export function useEquiposProteccion(params?: Params) {
  return useQuery({
    queryKey: clavesSso.epp.equipos.lista(params),
    queryFn: () => equiposProteccionService.listar(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useEquipoProteccionMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    // El nombre y el código del equipo se ven en las entregas, en el kit del
    // servidor y en el EPP requerido del puesto: todo lo que cuelga de `epp`
    // mostraba el nombre anterior hasta recargar la página.
    qc.invalidateQueries({ queryKey: clavesSso.epp.todo })
    qc.invalidateQueries({ queryKey: clavesSso.tablero.todo })
  }

  const crear = useMutation({
    mutationFn: (data: Partial<EquipoProteccion>) => equiposProteccionService.crear(data),
    onSuccess: () => {
      notificar.exito(
        'Equipo registrado',
        'El equipo de protección fue registrado correctamente.',
      )
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar el equipo'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<EquipoProteccion> }) =>
      equiposProteccionService.actualizar(id, data),
    onSuccess: () => {
      notificar.exito('Equipo actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar el equipo'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => equiposProteccionService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Equipo eliminado', 'El registro fue eliminado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar el equipo'),
  })

  return { crear, editar, eliminar }
}
