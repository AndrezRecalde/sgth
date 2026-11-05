import { useMutation, useQueryClient } from '@tanstack/react-query'
import { cargoService } from '../services/cargoService'
import type { CargoFormData } from '../schemas/cargo.schema'
import { notificar } from '@/components/ui'

export function useCargoMutations() {
  const qc = useQueryClient()

  // El nombre del cargo es el de sus puestos: también en el organigrama.
  const invalidar = () => {
    for (const queryKey of [['cargos'], ['puestos'], ['organigrama']]) {
      qc.invalidateQueries({ queryKey })
    }
  }

  const crear = useMutation({
    mutationFn: (data: CargoFormData) => cargoService.crear(data),
    onSuccess: () => {
      notificar.exito('Cargo creado', 'El cargo fue registrado correctamente.')
      invalidar()
    },
    // El modal pone en su campo el error de validación; el resto se notifica.
    onError: notificar.alFallarSalvoCampos('No se pudo crear el cargo'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<CargoFormData> }) =>
      cargoService.actualizar(id, data),
    onSuccess: () => {
      notificar.exito('Cargo actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo actualizar el cargo'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => cargoService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Cargo eliminado', 'El cargo fue eliminado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar el cargo'),
  })

  return { crear, editar, eliminar }
}
