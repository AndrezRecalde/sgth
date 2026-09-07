import { useMutation } from '@tanstack/react-query'
import { useRouter } from 'next/navigation'
import { notifications } from '@mantine/notifications'
import { IconX } from '@tabler/icons-react'
import { authService } from '../services/authService'
import { useAuth } from '@/hooks/useAuth'
import { destinoSeguro } from '@/lib/destino'
import type { AxiosError } from 'axios'
import type { ApiResponse, LoginResponse } from '@/types/api'
import React from 'react'

export function useLogin() {
  const router = useRouter()
  const { setAuth } = useAuth()

  /**
   * A dónde quería ir quien tuvo que iniciar sesión primero. Lo pone el proxy
   * al desviar aquí, y lo usa sobre todo el QR del permiso: se escanea desde
   * el celular, casi nunca hay sesión abierta, y sin esto se acaba en el
   * portal sin el permiso que se venía a resolver.
   *
   * Se lee al navegar y no con `useSearchParams()`, que obligaría a envolver
   * el formulario en un `Suspense` para no romper el renderizado estático de
   * la pantalla de acceso. Aquí solo corre tras un envío correcto, ya en el
   * navegador.
   */
  const destinoTrasAcceder = (): string => {
    const destino = destinoSeguro(
      new URLSearchParams(window.location.search).get('next')
    )

    return destino === '/' ? '/portal' : destino
  }

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
        router.push(destinoTrasAcceder())
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
