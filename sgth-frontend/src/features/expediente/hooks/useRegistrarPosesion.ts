import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { posesionService } from '../services/posesionService'
import type { RegistrarPosesionData } from '../services/movimiento.types'

/**
 * Registra la posesión (fase 6.4). Los errores de campo los pinta el modal;
 * el resto —fuera de fecha, ya registrada, sobre sí mismo— va a la
 * notificación.
 */
export function useRegistrarPosesion() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, ...datos }: { id: number } & RegistrarPosesionData) => posesionService.registrar(id, datos),
    onSuccess: (m) => {
      notificar.exito(
        'Posesión registrada',
        m.posesion_fuera_de_plazo
          ? 'Se registró fuera del plazo para presentarse: decida si el nombramiento sigue.'
          : 'Queda en la acción y la imprime el documento.',
      )
      qc.invalidateQueries({ queryKey: ['movimiento'] })
      qc.invalidateQueries({ queryKey: ['movimientos'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
    },
    onError: notificar.alFallar('No se pudo registrar la posesión'),
  })
}
