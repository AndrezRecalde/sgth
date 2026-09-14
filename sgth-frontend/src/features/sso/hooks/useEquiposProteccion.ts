import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import type { EquipoProteccion } from '../services/ssoService'
import { notificar } from '@/components/ui'

interface Params {
  page?: number
  tipo?: string
  estado?: boolean
}

export function useEquiposProteccion(params?: Params) {
  return useQuery({
    queryKey: ['sso-equipos-proteccion', params],
    queryFn: () => ssoService.listarEquiposProteccion(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useEquipoProteccionMutations() {
  const qc = useQueryClient()

  const invalidar = () => qc.invalidateQueries({ queryKey: ['sso-equipos-proteccion'] })

  const crear = useMutation({
    mutationFn: (data: Partial<EquipoProteccion>) => ssoService.crearEquipoProteccion(data),
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
      ssoService.actualizarEquipoProteccion(id, data),
    onSuccess: () => {
      notificar.exito('Equipo actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar el equipo'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarEquipoProteccion(id),
    onSuccess: () => {
      notificar.exito('Equipo eliminado', 'El registro fue eliminado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar el equipo'),
  })

  return { crear, editar, eliminar }
}
