import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import {
  ausenciaTemporalService, type FiltrosAusencia, type ReintegrarData,
} from '../services/ausenciaTemporalService'

export function useAusenciasTemporales(filtros: FiltrosAusencia = {}) {
  return useQuery({
    queryKey: ['ausencias-temporales', filtros],
    queryFn: () => ausenciaTemporalService.listar(filtros),
    staleTime: 1000 * 60,
  })
}

/**
 * Prepara el reintegro de una ausencia (fase 2.4). Queda en borrador en la
 * bandeja, donde sigue el trámite de cualquier acción.
 */
export function useReintegrarAusencia() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ ausenciaId, data }: { ausenciaId: number; data: ReintegrarData }) =>
      ausenciaTemporalService.reintegrar(ausenciaId, data),
    onSuccess: () => {
      notificar.exito(
        'Reintegro preparado',
        'Quedó en borrador en la bandeja de acciones de personal, para suscribirlo y registrarlo.',
      )
      qc.invalidateQueries({ queryKey: ['ausencias-temporales'] })
      qc.invalidateQueries({ queryKey: ['movimientos'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
    },
    // Los errores de campo los pinta el formulario; lo demás —una ausencia que
    // ya tiene reintegro, una fecha fuera del período— llega como mensaje.
    onError: notificar.alFallarSalvoCampos('No se pudo preparar el reintegro'),
  })
}
