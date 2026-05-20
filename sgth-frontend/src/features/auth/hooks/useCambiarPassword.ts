import { useMutation } from '@tanstack/react-query'
import { useRouter } from 'next/navigation'
import { notifications } from '@mantine/notifications'
import { IconX, IconCheck } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { authService } from '../services/authService'
import type { ApiResponse } from '@/types/api'
import type { CambiarPasswordFormData } from '../schemas/cambiarPassword.schema'

export function useCambiarPassword() {
  const router = useRouter()

  return useMutation({
    mutationFn: (data: CambiarPasswordFormData) =>
      authService.cambiarPassword({
        nueva_contrasena: data.nueva_contrasena,
      }),
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
