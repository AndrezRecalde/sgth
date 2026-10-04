import { useMutation, useQueryClient } from '@tanstack/react-query'
import { vinculacionInicialService } from '../services/vinculacionInicialService'
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
    },
    // Los errores con campo los pone el modal bajo cada uno; aquí, el resto.
    onError: notificar.alFallarSalvoCampos('No se pudo registrar la vinculación inicial'),
  })
}
