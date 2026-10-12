import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import {
  revisionClasificacionService, type CausalRevision, type PuestoDeLaRevision, type RevisionClasificacionData,
} from '../services/revisionClasificacionService'

/**
 * Cómo quedaría cada puesto y cuánto cobraría cada ocupante, y qué lo impide
 * (fase 7.1b). Se vuelve a pedir con cada cambio de la causal o de un puesto.
 */
export function useVistaPreviaRevision(causal: CausalRevision, puestos: PuestoDeLaRevision[]) {
  return useQuery({
    queryKey: ['revision-clasificacion', causal, puestos],
    queryFn: () => revisionClasificacionService.previsualizar(causal, puestos),
    enabled: puestos.length > 0,
    staleTime: 0,
    placeholderData: keepPreviousData,
  })
}

/** Prepara el lote: un borrador por ocupante de cada puesto. */
export function usePrepararRevision() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: RevisionClasificacionData) => revisionClasificacionService.preparar(data),
    onSuccess: (lote) => {
      notificar.exito(
        `Revisión preparada: lote #${lote.id}`,
        `${lote.total} acto(s) en borrador. Suscríbalos y regístrelos en Acciones de Personal, «Actos en lote».`,
      )
      qc.invalidateQueries({ queryKey: ['puestos'] })
      qc.invalidateQueries({ queryKey: ['lotes-accion'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
    },
    onError: notificar.alFallarSalvoCampos('No se pudo preparar la revisión'),
  })
}
