import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { cuentaBancariaService } from '../services/cuentaBancariaService'
import type { CuentaBancariaFormData } from '../schemas/cuentaBancaria.schema'
import type { ApiResponse } from '@/types/api'

export function useCuentaBancariaMutations(servidorId: number) {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['cuentas-bancarias', servidorId] })

  const onError = (error: AxiosError<ApiResponse>) => {
    notifications.show({
      title: 'Error',
      message: error.response?.data?.mensaje ?? 'Error inesperado',
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })
  }

  const crear = useMutation({
    mutationFn: (data: CuentaBancariaFormData) =>
      cuentaBancariaService.crear(servidorId, data),
    onSuccess: () => {
      notifications.show({
        title: 'Cuenta registrada',
        message: 'La cuenta bancaria fue registrada.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const setPrincipal = useMutation({
    mutationFn: ({ id, proposito }: { id: number; proposito: 'sueldo' | 'viatico' }) =>
      cuentaBancariaService.setPrincipal(servidorId, id, proposito),
    onSuccess: () => {
      notifications.show({
        title: 'Cuenta principal actualizada',
        message: 'La cuenta fue marcada como principal.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      cuentaBancariaService.eliminar(servidorId, id),
    onSuccess: () => {
      notifications.show({
        title: 'Cuenta eliminada',
        message: 'La cuenta fue eliminada correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { crear, setPrincipal, eliminar }
}
