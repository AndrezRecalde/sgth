import { useMutation, useQueryClient } from '@tanstack/react-query'
import { expedienteService } from '../services/expedienteService'
import { notificar } from '@/components/ui'

export function useDeclaracionMutations(servidorId: number) {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['declaraciones', servidorId] })

  const crear = useMutation({
    mutationFn: (data: Parameters<typeof expedienteService.crearDeclaracion>[1]) =>
      expedienteService.crearDeclaracion(servidorId, data),
    onSuccess: () => {
      notificar.exito('Declaración registrada', 'La declaración juramentada fue registrada.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar la declaración'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      expedienteService.eliminarDeclaracion(servidorId, id),
    onSuccess: () => {
      notificar.exito('Declaración eliminada', 'La declaración fue eliminada.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la declaración'),
  })

  const exportar = () =>
    expedienteService.exportarDeclaraciones(servidorId)

  return { crear, eliminar, exportar }
}
