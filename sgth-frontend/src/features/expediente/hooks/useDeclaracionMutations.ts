import { useMutation, useQueryClient } from '@tanstack/react-query'
import { declaracionService } from '../services/declaracionService'
import type { DeclaracionFormData } from '../schemas/declaracion.schema'
import { notificar } from '@/components/ui'

type Guardar = { data: DeclaracionFormData; documento: File | null }

export function useDeclaracionMutations(servidorId: number) {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['declaraciones', servidorId] })

  const crear = useMutation({
    mutationFn: ({ data, documento }: Guardar) =>
      declaracionService.crear(servidorId, data, documento),
    onSuccess: () => {
      notificar.exito('Declaración registrada', 'La declaración juramentada fue registrada.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar la declaración'),
  })

  // Antes la edición llamaba al servicio directo y un fallo se tragaba sin
  // aviso; tampoco mostraba que estaba guardando.
  const editar = useMutation({
    mutationFn: ({ id, data, documento }: Guardar & { id: number }) =>
      declaracionService.editar(servidorId, id, data, documento),
    onSuccess: () => {
      notificar.exito('Declaración actualizada', 'La declaración fue actualizada correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar la declaración'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => declaracionService.eliminar(servidorId, id),
    onSuccess: () => {
      notificar.exito('Declaración eliminada', 'La declaración fue eliminada.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la declaración'),
  })

  return { crear, editar, eliminar }
}
