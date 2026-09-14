import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  actividadLaboralService, type ReprogramarPlazoData,
} from '../services/actividadLaboralService'
import { getApiErrorMessage } from '@/types/api'
import { notificar } from '@/components/ui'

export function useContratoMutations(servidorId: number) {
  const qc = useQueryClient()

  const reprogramarPlazo = useMutation({
    mutationFn: ({ contratoId, ...datos }: { contratoId: number } & ReprogramarPlazoData) =>
      actividadLaboralService.reprogramarPlazo(servidorId, contratoId, datos),
    onSuccess: () => {
      notificar.exito(
        'Plazo reprogramado',
        'La nueva fecha de vencimiento quedó registrada con su motivo.',
      )
      qc.invalidateQueries({ queryKey: ['actividad-laboral'] })
      // El vencimiento decide cuándo cesa el servidor: cambiarlo altera lo que
      // muestran su ficha y el listado.
      qc.invalidateQueries({ queryKey: ['servidor'] })
      qc.invalidateQueries({ queryKey: ['servidores'] })
      qc.invalidateQueries({ queryKey: ['contratos'] })
    },
    onError: (error) => {
      notificar.error(
        'No se pudo reprogramar el plazo del contrato',
        getApiErrorMessage(error, 'Inténtalo de nuevo en unos segundos.'),
      )
    },
  })

  return { reprogramarPlazo }
}
