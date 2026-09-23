import { useMutation } from '@tanstack/react-query'
import { useRouter } from 'next/navigation'
import type { AxiosError } from 'axios'
import { authService } from '../services/authService'
import type { ApiResponse } from '@/types/api'
import type { CambiarPasswordFormData } from '../schemas/cambiarPassword.schema'
import { notificar } from '@/components/ui'
import { ROUTES } from '@/config/routes'

export function useCambiarPassword() {
  const router = useRouter()

  const deleteCookie = (name: string) => {
    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`
  }

  return useMutation({
    mutationFn: (data: CambiarPasswordFormData) =>
      authService.cambiarPassword({
        nueva_contrasena: data.nueva_contrasena,
      }),
    onSuccess: () => {
      deleteCookie('sgth_primer_login')
      notificar.exito(
        'Contraseña actualizada',
        'Su contraseña ha sido cambiada exitosamente.',
      )
      router.push(ROUTES.PORTAL.HOME)
    },
    onError: (error: AxiosError<ApiResponse>) => {
      notificar.error(
        'No se pudo cambiar la contraseña',
        error.response?.data?.mensaje ?? 'Error inesperado.',
      )
    },
  })
}
