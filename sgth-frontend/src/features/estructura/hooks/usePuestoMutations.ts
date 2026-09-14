import { useMutation, useQueryClient } from '@tanstack/react-query'
import { puestosExtensionesService } from '../services/puestosExtensionesService'
import type { PuestoFormData } from '../schemas/puesto.schema'
import { notificar } from '@/components/ui'

export function usePuestoMutations() {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['puestos'] })

  const crear = useMutation({
    mutationFn: (data: PuestoFormData) =>
      puestosExtensionesService.crearPuesto(data),
    onSuccess: () => {
      notificar.exito('Puesto creado', 'El puesto fue registrado correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo crear el puesto'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: PuestoFormData }) =>
      puestosExtensionesService.editarPuesto(id, data),
    onSuccess: () => {
      notificar.exito('Puesto actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar el puesto'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => puestosExtensionesService.eliminarPuesto(id),
    onSuccess: () => {
      notificar.exito('Puesto eliminado', 'El puesto fue eliminado correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar el puesto'),
  })

  return { crear, editar, eliminar }
}
