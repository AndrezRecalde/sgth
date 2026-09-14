import { useMutation, useQueryClient } from '@tanstack/react-query'
import { puestosExtensionesService } from '../services/puestosExtensionesService'
import type { ExtensionFormData } from '../schemas/extension.schema'
import { notificar } from '@/components/ui'

export function useExtensionMutations() {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['directorio'] })

  const crear = useMutation({
    mutationFn: (data: ExtensionFormData) =>
      puestosExtensionesService.crearExtension(data),
    onSuccess: () => {
      notificar.exito('Extensión registrada', 'La extensión fue creada correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar la extensión'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: ExtensionFormData }) =>
      puestosExtensionesService.editarExtension(id, data),
    onSuccess: () => {
      notificar.exito('Extensión actualizada', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar la extensión'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      puestosExtensionesService.eliminarExtension(id),
    onSuccess: () => {
      notificar.exito('Extensión eliminada', 'La extensión fue eliminada.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la extensión'),
  })

  return { crear, editar, eliminar }
}
