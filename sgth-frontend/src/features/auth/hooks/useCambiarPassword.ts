import { useMutation } from '@tanstack/react-query'
import { useRouter } from 'next/navigation'
import { authService } from '../services/authService'
import { getApiErrorMessage } from '@/types/api'
import type { CambiarPasswordFormData } from '../schemas/cambiarPassword.schema'
import { notificar } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { borrarCookie } from '@/lib/cookies'

export function useCambiarPassword() {
  const router = useRouter()

  return useMutation({
    mutationFn: (data: CambiarPasswordFormData) =>
      authService.cambiarPassword({
        nueva_contrasena: data.nueva_contrasena,
      }),
    onSuccess: () => {
      borrarCookie('sgth_primer_login')
      notificar.exito(
        'Contraseña actualizada',
        'Su contraseña ha sido cambiada exitosamente.',
      )
      router.push(ROUTES.PORTAL.HOME)
    },
    // El motivo concreto —la cédula, la misma clave— viaja en `errores`; el
    // `mensaje` de un 422 es el genérico «Los datos enviados no son válidos».
    onError: (error) => {
      notificar.error(
        'No se pudo cambiar la contraseña',
        getApiErrorMessage(error, 'Error inesperado.'),
      )
    },
  })
}
