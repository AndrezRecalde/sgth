import { useMutation } from '@tanstack/react-query'
import { useRouter } from 'next/navigation'
import { authService } from '../services/authService'
import { useAuth } from '@/hooks/useAuth'
import { destinoSeguro } from '@/lib/destino'
import { borrarCookie, escribirCookie } from '@/lib/cookies'
import { DURACION_SESION_DIAS } from '@/store/auth.store'
import { getApiErrorMessage, type LoginResponse } from '@/types/api'
import { notificar } from '@/components/ui'
import { ROUTES } from '@/config/routes'

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

    return destino === '/' ? ROUTES.PORTAL.HOME : destino
  }

  return useMutation({
    mutationFn: authService.login,
    onSuccess: (data: LoginResponse) => {
      setAuth(data.token, data.usuario)

      if (data.primer_login) {
        escribirCookie('sgth_primer_login', 'true', DURACION_SESION_DIAS)
        router.push(ROUTES.AUTH.CAMBIAR_PASSWORD)
      } else {
        borrarCookie('sgth_primer_login')
        router.push(destinoTrasAcceder())
      }
    },
    onError: (error) => {
      notificar.error(
        'No se pudo iniciar sesión',
        getApiErrorMessage(error, 'Error inesperado. Intente nuevamente.'),
      )
    },
  })
}
