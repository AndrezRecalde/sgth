import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { puestosExtensionesService } from '../services/puestosExtensionesService'
import type { PuestoFormData } from '../schemas/puesto.schema'
import type { ApiResponse } from '@/types/api'
import { notificar } from '@/components/ui'

export function usePuestoMutations() {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['puestos'] })

  const onError = (error: AxiosError<ApiResponse>) => {
    notificar.error('Error', error.response?.data?.mensaje ?? 'Error inesperado')
  }

  const crear = useMutation({
    mutationFn: (data: PuestoFormData) =>
      puestosExtensionesService.crearPuesto(data),
    onSuccess: () => {
      notificar.exito('Puesto creado', 'El puesto fue registrado correctamente.')
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: PuestoFormData }) =>
      puestosExtensionesService.editarPuesto(id, data),
    onSuccess: () => {
      notificar.exito('Puesto actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => puestosExtensionesService.eliminarPuesto(id),
    onSuccess: () => {
      notificar.exito('Puesto eliminado', 'El puesto fue eliminado correctamente.')
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
