import { useMutation, useQueryClient } from '@tanstack/react-query'
import { cargoService } from '../services/cargoService'
import type { CargoFormData } from '@/types/api'
import { notificar } from '@/components/ui'

export function useCargoMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['cargos'] })
    qc.invalidateQueries({ queryKey: ['puestos'] })
  }

  const crear = useMutation({
    mutationFn: (data: CargoFormData) => cargoService.crear(data),
    onSuccess: () => {
      notificar.exito('Cargo creado', 'El cargo fue registrado correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo crear el cargo'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<CargoFormData> }) =>
      cargoService.actualizar(id, data),
    onSuccess: () => {
      notificar.exito('Cargo actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar el cargo'),
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
