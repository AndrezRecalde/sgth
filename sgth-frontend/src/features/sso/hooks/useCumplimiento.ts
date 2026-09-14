import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import { notificar } from '@/components/ui'

export function useListaVerificacion(periodo: string | null) {
  return useQuery({
    queryKey: ['sso-lista-verificacion', periodo],
    queryFn: () => ssoService.listaVerificacionCumplimiento(periodo!),
    enabled: !!periodo,
    staleTime: 1000 * 30,
  })
}

export function useCumplimientoMutations() {
  const qc = useQueryClient()

  const registrar = useMutation({
    mutationFn: (data: { normativa_legal_sso_id: number; periodo: string; estado: string; observaciones?: string }) =>
      ssoService.registrarCumplimiento(data),
    onSuccess: () => {
      notificar.exito(
        'Cumplimiento registrado',
        'El estado de cumplimiento fue actualizado.',
      )
      qc.invalidateQueries({ queryKey: ['sso-lista-verificacion'] })
    },
    onError: notificar.alFallar('No se pudo registrar el cumplimiento'),
  })

  return { registrar }
}
