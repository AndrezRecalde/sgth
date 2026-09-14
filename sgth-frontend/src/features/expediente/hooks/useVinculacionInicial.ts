import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { vinculacionInicialService } from '../services/vinculacionInicialService'
import { getApiErrorMessage } from '@/types/api'
import { useAuth } from '@/hooks/useAuth'
import { notificar } from '@/components/ui'

/** Permiso que habilita la carga inicial. Se revoca al terminar la migración. */
export const PERMISO_VINCULACION_INICIAL = 'vincular-servidor-inicial'

export function usePuedeVincularInicial(): boolean {
  const { hasPermiso } = useAuth()
  return hasPermiso(PERMISO_VINCULACION_INICIAL)
}

export function useVinculacionInicial() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: vinculacionInicialService.registrar,
    onSuccess: () => {
      notificar.exito(
        'Servidor vinculado',
        'Se registró la ficha y su contrato vigente. Quedó marcado como carga inicial.',
      )
      qc.invalidateQueries({ queryKey: ['servidores'] })
      qc.invalidateQueries({ queryKey: ['vinculacion-inicial'] })
    },
    onError: (error) => {
      notificar.error(
        'No se pudo registrar',
        getApiErrorMessage(error, 'No se pudo registrar la vinculación inicial.'),
      )
    },
  })
}

export function useVinculosCargados(habilitado = true) {
  return useQuery({
    queryKey: ['vinculacion-inicial'],
    queryFn: vinculacionInicialService.listar,
    enabled: habilitado,
    staleTime: 1000 * 60,
  })
}
