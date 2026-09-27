import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { getApiErrorMessage } from '@/types/api'
import { movimientoService } from '../services/movimientoService'
import type { MovimientoFormData } from '../schemas/movimiento.schema'
import { admiteMarcacion } from '../utils/nombramiento'

/**
 * Los campos de contratación solo viajan en el ingreso. Enviarlos en un traspaso
 * los grabaría en una acción que nunca creará un contrato, y el documento
 * impreso acabaría con un número que no corresponde a nada.
 */
function soloLoQueAplica(data: MovimientoFormData): MovimientoFormData {
  if (data.tipo_movimiento === 'ingreso') {
    // Cinturón, además del `setValue` al cambiar de nombramiento: un borrador
    // que ya venía con la marcación puesta y un nombramiento que no marca se
    // edita sin tocar ese selector, y entonces el `onChange` no corre. La
    // modalidad manda sobre lo que quedó guardado.
    return admiteMarcacion(data.tipo_nombramiento_propuesto)
      ? data
      : { ...data, puede_marcar: false }
  }

  return {
    ...data,
    numero_contrato: null,
    fecha_fin_propuesta: null,
    puede_marcar: null,
    tipo_nombramiento_propuesto: null,
    cubre_movimiento_id: null,
  }
}

/**
 * Registra una acción de personal nueva o guarda los cambios de un borrador.
 *
 * Es la misma mutación para los dos casos porque es el mismo formulario: lo
 * único que cambia es el endpoint y lo que se dice al terminar.
 */
export function useGuardarAccionPersonal({
  servidorId,
  movimientoId,
  onGuardado,
}: {
  servidorId: number
  /** Presente = se edita un borrador; ausente = se registra uno nuevo. */
  movimientoId?: number | null
  onGuardado: () => void
}) {
  const qc = useQueryClient()
  const edicion = movimientoId != null

  return useMutation({
    mutationFn: (data: MovimientoFormData) => {
      const limpio = soloLoQueAplica(data)

      if (edicion) {
        // tipo y subtipo no se envían: el backend los rechaza en la edición
        // porque cambiar la naturaleza del acto exige anular y registrar otro.
        const editable = { ...limpio }
        delete (editable as Partial<MovimientoFormData>).tipo_movimiento
        delete (editable as Partial<MovimientoFormData>).subtipo_movimiento

        return movimientoService.actualizarBorrador(movimientoId, editable)
      }

      return movimientoService.crear(servidorId, limpio)
    },
    onSuccess: () => {
      notificar.exito(
        edicion ? 'Borrador actualizado' : 'Acción de personal registrada',
        edicion
          ? 'Los cambios quedaron guardados en la acción de personal.'
          : 'Quedó en borrador, pendiente de revisión y aprobación.',
      )
      qc.invalidateQueries({ queryKey: ['movimientos'] })
      qc.invalidateQueries({ queryKey: ['movimiento'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
      onGuardado()
    },
    onError: (error) => {
      notificar.error(
        edicion ? 'No se pudo guardar' : 'No se pudo registrar',
        getApiErrorMessage(
          error,
          edicion
            ? 'No se pudo actualizar el borrador.'
            : 'No se pudo registrar la acción de personal.',
        ),
      )
    },
  })
}
