import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import { notificar } from '@/components/ui'

export function useFactoresRiesgo(params?: { categoria?: string; search?: string }) {
  return useQuery({
    queryKey: ['sso-factores-riesgo', params],
    queryFn: () => ssoService.listarFactoresRiesgo(params),
    staleTime: 1000 * 60 * 10,
  })
}

export function useFactorRiesgoMutations() {
  const qc = useQueryClient()

  const invalidar = () => qc.invalidateQueries({ queryKey: ['sso-factores-riesgo'] })

  const crear = useMutation({
    mutationFn: (data: { nombre: string; categoria: string }) => ssoService.crearFactorRiesgo(data),
    onSuccess: () => {
      notificar.exito('Factor de riesgo registrado', 'El factor fue agregado al catálogo.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar el factor de riesgo'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarFactorRiesgo(id),
    onSuccess: () => {
      notificar.exito('Factor eliminado', 'El factor fue eliminado del catálogo.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar el factor de riesgo'),
  })

  return { crear, eliminar }
}
