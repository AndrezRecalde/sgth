import { useMutation, useQueryClient } from '@tanstack/react-query'
import { partidaPresupuestariaService } from '../services/partidaPresupuestariaService'
import type { PartidaPresupuestariaFormData } from '@/types/api'
import { notificar } from '@/components/ui'

export function usePartidaPresupuestariaMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['partidas-presupuestarias'] })
    // Los puestos embeben la partida en sus respuestas.
    qc.invalidateQueries({ queryKey: ['puestos'] })
  }

  const crear = useMutation({
    mutationFn: (data: PartidaPresupuestariaFormData) =>
      partidaPresupuestariaService.crear(data),
    onSuccess: () => {
      notificar.exito(
        'Partida creada',
        'La partida presupuestaria fue registrada correctamente.',
      )
      invalidar()
    },
    onError: notificar.alFallar('No se pudo crear la partida'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<PartidaPresupuestariaFormData> }) =>
      partidaPresupuestariaService.actualizar(id, data),
    onSuccess: () => {
      notificar.exito('Partida actualizada', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar la partida'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => partidaPresupuestariaService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Partida eliminada', 'La partida presupuestaria fue eliminada.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la partida'),
  })

  return { crear, editar, eliminar }
}
