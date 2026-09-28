import { useMutation, useQueryClient } from '@tanstack/react-query'
import { subrogacionService } from '../services/subrogacionService'
import type { SubrogacionFormData } from '../schemas/subrogacion.schema'
import { notificar } from '@/components/ui'

export function useSubrogacionMutations() {
  const qc = useQueryClient()

  /**
   * Una subrogación no vive sola: cada una de estas tres operaciones escribe
   * también en `movimientos_personal` —registrar crea la Acción de Personal que
   * la respalda, y finalizar deja su constancia de cierre anticipado—, así que
   * la bandeja de acciones y el historial del servidor se quedaban mostrando lo
   * de antes hasta que su `staleTime` venciera.
   */
  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['subrogaciones-vigentes'] })
    // ['movimientos'] es prefijo de ['movimientos', servidorId], así que
    // alcanza también al historial de cada servidor.
    qc.invalidateQueries({ queryKey: ['movimientos'] })
    qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
    // El organigrama pinta quién ejerce hoy por subrogación.
    qc.invalidateQueries({ queryKey: ['unidades'] })
    qc.invalidateQueries({ queryKey: ['unidades-todas'] })
  }

  const registrar = useMutation({
    mutationFn: (data: SubrogacionFormData) => subrogacionService.registrar(data),
    onSuccess: () => {
      notificar.exito('Subrogación registrada', 'La subrogación/encargo fue registrado correctamente.')
      invalidar()
    },
    // Los errores por campo los pinta el formulario debajo de cada casilla
    // (regla 07): notificarlos además obliga a leer lo mismo dos veces.
    onError: notificar.alFallarSalvoCampos('No se pudo registrar la subrogación'),
  })

  const finalizar = useMutation({
    mutationFn: (id: number) => subrogacionService.finalizar(id),
    onSuccess: () => {
      notificar.exito('Subrogación finalizada', 'La subrogación/encargo fue finalizado correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo finalizar la subrogación'),
  })

  const cancelar = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      subrogacionService.cancelar(id, motivo),
    onSuccess: () => {
      notificar.exito('Subrogación cancelada', 'La subrogación/encargo fue cancelado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo cancelar la subrogación'),
  })

  return { registrar, finalizar, cancelar }
}
