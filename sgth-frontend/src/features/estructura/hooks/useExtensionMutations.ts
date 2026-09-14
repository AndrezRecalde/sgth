import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { puestosExtensionesService } from '../services/puestosExtensionesService'
import type { ExtensionFormData } from '../schemas/extension.schema'
import type { ApiResponse } from '@/types/api'
import { notificar } from '@/components/ui'

export function useExtensionMutations() {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['directorio'] })

  const onError = (error: AxiosError<ApiResponse>) => {
    notificar.error('Error', error.response?.data?.mensaje ?? 'Error inesperado')
  }

  const crear = useMutation({
    mutationFn: (data: ExtensionFormData) =>
      puestosExtensionesService.crearExtension(data),
    onSuccess: () => {
      notificar.exito('Extensión registrada', 'La extensión fue creada correctamente.')
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: ExtensionFormData }) =>
      puestosExtensionesService.editarExtension(id, data),
    onSuccess: () => {
      notificar.exito('Extensión actualizada', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      puestosExtensionesService.eliminarExtension(id),
    onSuccess: () => {
      notificar.exito('Extensión eliminada', 'La extensión fue eliminada.')
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
