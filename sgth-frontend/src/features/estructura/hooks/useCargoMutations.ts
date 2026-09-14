import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { cargoService } from '../services/cargoService'
import type { ApiResponse, CargoFormData } from '@/types/api'
import { notificar } from '@/components/ui'

export function useCargoMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['cargos'] })
    qc.invalidateQueries({ queryKey: ['puestos'] })
  }

  const onError = (error: AxiosError<ApiResponse>) => {
    notificar.error('Error', error.response?.data?.mensaje ?? 'Error inesperado')
  }

  const crear = useMutation({
    mutationFn: (data: CargoFormData) => cargoService.crear(data),
    onSuccess: () => {
      notificar.exito('Cargo creado', 'El cargo fue registrado correctamente.')
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<CargoFormData> }) =>
      cargoService.actualizar(id, data),
    onSuccess: () => {
      notificar.exito('Cargo actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => cargoService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Cargo eliminado', 'El cargo fue eliminado.')
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
