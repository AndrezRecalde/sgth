import { useMutation } from '@tanstack/react-query'
import { useRouter } from 'next/navigation'
import { notifications } from '@mantine/notifications'
import { IconX, IconCheck } from '@tabler/icons-react'
import { authService } from '../services/authService'
import type { AxiosError } from 'axios'
import type { ApiResponse } from '@/types/api'
import React from 'react'

export function useCambiarPassword() {
  const router = useRouter()

  return useMutation({
    mutationFn: authService.cambiarPassword,
    onSuccess: () => {
      notifications.show({
        title: 'Contraseña actualizada',
        message: 'Su contraseña ha sido cambiada exitosamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      router.push('/')
    },
    onError: (error: AxiosError<ApiResponse>) => {
      notifications.show({
        title: 'Error al cambiar contraseña',
        message: error.response?.data?.mensaje ?? 'Error inesperado.',
        color: 'red',
        icon: React.createElement(IconX, { size: 16 }),
      })
    },
  })
}
