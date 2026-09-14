import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import { getApiErrorMessage } from '@/types/api'
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

  const onError = (error: unknown) =>
    notificar.error('Error', getApiErrorMessage(error))

  const crear = useMutation({
    mutationFn: (data: Partial<EquipoProteccion>) => ssoService.crearEquipoProteccion(data),
    onSuccess: () => {
      notificar.exito(
        'Equipo registrado',
        'El equipo de protección fue registrado correctamente.',
      )
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<EquipoProteccion> }) =>
      ssoService.actualizarEquipoProteccion(id, data),
    onSuccess: () => {
      notificar.exito('Equipo actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarEquipoProteccion(id),
    onSuccess: () => {
      notificar.exito('Equipo eliminado', 'El registro fue eliminado.')
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
