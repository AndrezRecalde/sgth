import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import { getApiErrorMessage } from '@/types/api'
import type { RiesgoLaboral } from '../services/ssoService'
import { notificar } from '@/components/ui'

interface Params {
  page?: number
  puesto_id?: number
  estado?: boolean
}

export function useRiesgosLaborales(params?: Params) {
  return useQuery({
    queryKey: ['sso-riesgos', params],
    queryFn: () => ssoService.listarRiesgos(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useRiesgoLaboralMutations() {
  const qc = useQueryClient()

  const invalidar = () => qc.invalidateQueries({ queryKey: ['sso-riesgos'] })

  const onError = (error: unknown) =>
    notificar.error('Error', getApiErrorMessage(error))

  const crear = useMutation({
    mutationFn: (data: Partial<RiesgoLaboral>) => ssoService.crearRiesgo(data),
    onSuccess: () => {
      notificar.exito('Riesgo laboral registrado', 'El riesgo fue registrado correctamente.')
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<RiesgoLaboral> }) =>
      ssoService.actualizarRiesgo(id, data),
    onSuccess: () => {
      notificar.exito('Riesgo laboral actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarRiesgo(id),
    onSuccess: () => {
      notificar.exito('Riesgo laboral eliminado', 'El registro fue eliminado.')
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
