import { useMutation, useQueryClient } from '@tanstack/react-query'
import { puestosExtensionesService } from '../services/puestosExtensionesService'
import type { PuestoFormData } from '../schemas/puesto.schema'
import { notificar } from '@/components/ui'

export function usePuestoMutations() {
  const qc = useQueryClient()

  // Un puesto cambia también la plantilla, el organigrama y el detalle de su
  // unidad: solo se refrescaba la lista, y la plantilla seguía con las plazas
  // de antes hasta cinco minutos después.
  const invalidar = () => {
    for (const queryKey of [['puestos'], ['plantilla'], ['organigrama'], ['unidad']]) {
      qc.invalidateQueries({ queryKey })
    }
  }

  const crear = useMutation({
    mutationFn: (data: PuestoFormData) =>
      puestosExtensionesService.crearPuesto(data),
    onSuccess: () => {
      notificar.exito('Puesto creado', 'El puesto fue registrado correctamente.')
      invalidar()
    },
    // El formulario pone en su campo el error de validación; el resto se notifica.
    onError: notificar.alFallarSalvoCampos('No se pudo crear el puesto'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: PuestoFormData }) =>
      puestosExtensionesService.editarPuesto(id, data),
    onSuccess: () => {
      notificar.exito('Puesto actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo actualizar el puesto'),
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
