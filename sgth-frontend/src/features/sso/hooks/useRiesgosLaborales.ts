import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
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

  const crear = useMutation({
    mutationFn: (data: Partial<RiesgoLaboral>) => ssoService.crearRiesgo(data),
    onSuccess: () => {
      notificar.exito('Riesgo laboral registrado', 'El riesgo fue registrado correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar el riesgo laboral'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<RiesgoLaboral> }) =>
      ssoService.actualizarRiesgo(id, data),
    onSuccess: () => {
      notificar.exito('Riesgo laboral actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar el riesgo laboral'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarRiesgo(id),
    onSuccess: () => {
      notificar.exito('Riesgo laboral eliminado', 'El registro fue eliminado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar el riesgo laboral'),
  })

  return { crear, editar, eliminar }
}
