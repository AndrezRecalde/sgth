import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import { getApiErrorMessage } from '@/types/api'
import { notificar } from '@/components/ui'

interface Params {
  page?: number
  periodo?: string
  unidad_administrativa_id?: number
}

export function useHorasTrabajadas(params?: Params) {
  return useQuery({
    queryKey: ['sso-horas-trabajadas', params],
    queryFn: () => ssoService.listarHorasTrabajadas(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useHorasTrabajadasMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['sso-horas-trabajadas'] })
    qc.invalidateQueries({ queryKey: ['sso-indicadores-reactivos'] })
  }

  const onError = (error: unknown) =>
    notificar.error('Error', getApiErrorMessage(error))

  const registrar = useMutation({
    mutationFn: (data: { periodo: string; unidad_administrativa_id?: number; total_horas: number }) =>
      ssoService.registrarHorasTrabajadas(data),
    onSuccess: () => {
      notificar.exito(
        'Horas registradas',
        'Las horas trabajadas del período fueron registradas.',
      )
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarHorasTrabajadas(id),
    onSuccess: () => {
      notificar.exito('Registro eliminado', 'El registro de horas trabajadas fue eliminado.')
      invalidar()
    },
    onError,
  })

  return { registrar, eliminar }
}
