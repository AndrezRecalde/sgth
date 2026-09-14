import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import { getApiErrorMessage } from '@/types/api'
import type { AccidenteTrabajo } from '../services/ssoService'
import { notificar } from '@/components/ui'

interface Params {
  page?: number
  servidor_id?: number
  estado?: boolean
}

export function useAccidentesTrabajo(params?: Params) {
  return useQuery({
    queryKey: ['sso-accidentes', params],
    queryFn: () => ssoService.listarAccidentes(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useAccidenteTrabajoMutations() {
  const qc = useQueryClient()

  const invalidar = () => qc.invalidateQueries({ queryKey: ['sso-accidentes'] })

  const onError = (error: unknown) =>
    notificar.error('Error', getApiErrorMessage(error))

  const crear = useMutation({
    mutationFn: (data: Partial<AccidenteTrabajo>) => ssoService.crearAccidente(data),
    onSuccess: () => {
      notificar.exito(
        'Accidente registrado',
        'El accidente de trabajo fue registrado correctamente.',
      )
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<AccidenteTrabajo> }) =>
      ssoService.actualizarAccidente(id, data),
    onSuccess: () => {
      notificar.exito('Accidente actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarAccidente(id),
    onSuccess: () => {
      notificar.exito('Accidente eliminado', 'El registro fue eliminado.')
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
