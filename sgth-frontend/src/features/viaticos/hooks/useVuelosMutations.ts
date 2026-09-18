import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { viaticoService } from '../services/viaticoService'

/**
 * Aprobar o rechazar la autorización de un vuelo.
 *
 * Al decidir cambia la lista, el contador de la pestaña «Vuelos por
 * autorizar» (sale del resumen de la bandeja) y el estado del vuelo en la
 * ficha del viático. Antes solo se refrescaba la lista y el contador seguía
 * contando el vuelo ya decidido.
 */
export function useVuelosMutations() {
  const qc = useQueryClient()

  const refrescar = () => {
    qc.invalidateQueries({ queryKey: ['vuelos-autorizacion'] })
    qc.invalidateQueries({ queryKey: ['viaticos', 'bandeja', 'resumen'] })
    qc.invalidateQueries({ queryKey: ['viatico'] })
  }

  const aprobar = useMutation({
    mutationFn: (id: number) => viaticoService.vuelos.aprobar(id),
    onSuccess: () => {
      notificar.exito('Vuelo autorizado', 'El servidor ya puede comprar el pasaje.')
      refrescar()
    },
    onError: notificar.alFallar('No se pudo autorizar el vuelo'),
  })

  const rechazar = useMutation({
    mutationFn: ({ id, observacion }: { id: number; observacion: string }) =>
      viaticoService.vuelos.rechazar(id, { observacion }),
    onSuccess: () => {
      notificar.exito('Vuelo rechazado', 'El servidor verá el motivo en su viático.')
      refrescar()
    },
    onError: notificar.alFallar('No se pudo rechazar el vuelo'),
  })

  return { aprobar, rechazar }
}
