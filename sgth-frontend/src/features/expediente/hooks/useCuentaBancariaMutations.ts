import { useMutation, useQueryClient } from '@tanstack/react-query'
import { cuentaBancariaService } from '../services/cuentaBancariaService'
import type { CuentaBancariaFormData } from '../schemas/cuentaBancaria.schema'
import { notificar } from '@/components/ui'

export function useCuentaBancariaMutations(servidorId: number) {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['cuentas-bancarias', servidorId] })

  const crear = useMutation({
    mutationFn: (data: CuentaBancariaFormData) =>
      cuentaBancariaService.crear(servidorId, data),
    onSuccess: () => {
      notificar.exito('Cuenta registrada', 'La cuenta bancaria fue registrada.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar la cuenta'),
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
    onError: notificar.alFallar('No se pudo cambiar la cuenta principal'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      cuentaBancariaService.eliminar(servidorId, id),
    onSuccess: () => {
      notificar.exito('Cuenta eliminada', 'La cuenta fue eliminada correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la cuenta'),
  })

  return { crear, setPrincipal, eliminar }
}
