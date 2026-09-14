import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { cuentaBancariaService } from '../services/cuentaBancariaService'
import type { CuentaBancariaFormData } from '../schemas/cuentaBancaria.schema'
import type { ApiResponse } from '@/types/api'
import { notificar } from '@/components/ui'

export function useCuentaBancariaMutations(servidorId: number) {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['cuentas-bancarias', servidorId] })

  const onError = (error: AxiosError<ApiResponse>) => {
    notificar.error('Error', error.response?.data?.mensaje ?? 'Error inesperado')
  }

  const crear = useMutation({
    mutationFn: (data: CuentaBancariaFormData) =>
      cuentaBancariaService.crear(servidorId, data),
    onSuccess: () => {
      notificar.exito('Cuenta registrada', 'La cuenta bancaria fue registrada.')
      invalidar()
    },
    onError,
  })

  const setPrincipal = useMutation({
    mutationFn: ({ id, proposito }: { id: number; proposito: 'sueldo' | 'viatico' }) =>
      cuentaBancariaService.setPrincipal(servidorId, id, proposito),
    onSuccess: () => {
      notificar.exito(
        'Cuenta principal actualizada',
        'La cuenta fue marcada como principal.',
      )
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      cuentaBancariaService.eliminar(servidorId, id),
    onSuccess: () => {
      notificar.exito('Cuenta eliminada', 'La cuenta fue eliminada correctamente.')
      invalidar()
    },
    onError,
  })

  return { crear, setPrincipal, eliminar }
}
