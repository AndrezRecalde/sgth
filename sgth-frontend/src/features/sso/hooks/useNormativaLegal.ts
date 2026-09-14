import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import { getApiErrorMessage } from '@/types/api'
import { notificar } from '@/components/ui'

export function useNormativas(params?: { tipo?: string; solo_activas?: boolean }) {
  return useQuery({
    queryKey: ['sso-normativas', params],
    queryFn: () => ssoService.listarNormativas(params),
    staleTime: 1000 * 60 * 10,
  })
}

export function useNormativaMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['sso-normativas'] })
    qc.invalidateQueries({ queryKey: ['sso-lista-verificacion'] })
  }

  const onError = (error: unknown) =>
    notificar.error('Error', getApiErrorMessage(error))

  const crear = useMutation({
    mutationFn: (data: { nombre: string; tipo: string; fecha_vigencia?: string; descripcion?: string }) =>
      ssoService.crearNormativa(data),
    onSuccess: () => {
      notificar.exito('Normativa registrada', 'La normativa fue agregada al catálogo.')
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarNormativa(id),
    onSuccess: () => {
      notificar.exito('Normativa eliminada', 'La normativa fue eliminada del catálogo.')
      invalidar()
    },
    onError,
  })

  return { crear, eliminar }
}
