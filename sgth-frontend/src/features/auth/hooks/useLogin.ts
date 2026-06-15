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

  const setCookie = (name: string, value: string, days = 1) => {
    const expires = new Date(Date.now() + days * 864e5).toUTCString()
    document.cookie = `${name}=${value}; expires=${expires}; path=/`
  }

  const deleteCookie = (name: string) => {
    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`
  }

  return useMutation({
    mutationFn: authService.login,
    onSuccess: (data: LoginResponse) => {
      setAuth(data.token, data.usuario)

      if (data.primer_login) {
        setCookie('sgth_primer_login', 'true')
        router.push('/cambiar-password')
      } else {
        deleteCookie('sgth_primer_login')
        router.push('/bienvenida')
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
