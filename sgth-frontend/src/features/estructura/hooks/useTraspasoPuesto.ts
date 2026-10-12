import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { traspasoPuestoService, type TraspasoPuestoData } from '../services/traspasoPuestoService'

/**
 * Quiénes recibirían el acto del traspaso y qué lo impide (fase 7.1a). Se
 * vuelve a pedir al cambiar la unidad de destino: la jefatura depende de ella.
 */
export function useVistaPreviaTraspaso(puestoId: number | null, unidadDestinoId: number | null) {
  return useQuery({
    queryKey: ['traspaso-puesto', puestoId, unidadDestinoId],
    queryFn: () => traspasoPuestoService.previsualizar(puestoId!, unidadDestinoId),
    enabled: puestoId != null,
    staleTime: 0,
    // Al cambiar la unidad, la lista de ocupantes no parpadea.
    placeholderData: keepPreviousData,
  })
}

/** Prepara el lote: un borrador por ocupante, que se registra desde Acciones de Personal. */
export function usePrepararTraspaso() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: TraspasoPuestoData) => traspasoPuestoService.preparar(data),
    onSuccess: (lote) => {
      notificar.exito(
        `Traspaso preparado: lote #${lote.id}`,
        `${lote.total} acto(s) en borrador. Suscríbalos y regístrelos en Acciones de Personal, «Actos en lote».`,
      )
      qc.invalidateQueries({ queryKey: ['puestos'] })
      qc.invalidateQueries({ queryKey: ['lotes-accion'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
    },
    onError: notificar.alFallarSalvoCampos('No se pudo preparar el traspaso'),
  })
}
