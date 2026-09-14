import { useMutation, useQueryClient } from '@tanstack/react-query'
import { estructuraService } from '../services/estructuraService'
import type { UnidadFormData } from '../schemas/unidad.schema'
import { notificar } from '@/components/ui'

export function useUnidadMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['organigrama'] })
    qc.invalidateQueries({ queryKey: ['unidades'] })
  }

  const crear = useMutation({
    mutationFn: (data: UnidadFormData) =>
      estructuraService.crearUnidad(data),
    onSuccess: () => {
      notificar.exito('Unidad creada', 'La unidad administrativa fue registrada.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo crear la unidad'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: UnidadFormData }) =>
      estructuraService.editarUnidad(id, data),
    onSuccess: () => {
      notificar.exito('Unidad actualizada', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar la unidad'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => estructuraService.eliminarUnidad(id),
    onSuccess: () => {
      notificar.exito('Unidad eliminada', 'La unidad fue eliminada correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la unidad'),
  })

  return { crear, editar, eliminar }
}
