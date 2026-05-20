import { useMutation } from '@tanstack/react-query'
import { useRouter } from 'next/navigation'
import { notifications } from '@mantine/notifications'
import { IconX } from '@tabler/icons-react'
import { authService } from '../services/authService'
import { useAuth } from '@/hooks/useAuth'
import type { AxiosError } from 'axios'
import type { ApiResponse, LoginResponse } from '@/types/api'
import React from 'react'

export function useLogin() {
  const router = useRouter()
  const { setAuth } = useAuth()

  return useMutation({
    mutationFn: authService.login,
    onSuccess: (data: LoginResponse) => {
      setAuth(data.token, data.usuario)
      if (data.primer_login) {
        router.push('/cambiar-password')
      } else {
        router.push('/')
      }
    },
    onError: (error: AxiosError<ApiResponse>) => {
      notifications.show({
        title: 'Error al iniciar sesión',
        message: error.response?.data?.mensaje ?? 'Error inesperado. Intente nuevamente.',
        color: 'red',
        icon: React.createElement(IconX, { size: 16 }),
      })
    },
  })
}
