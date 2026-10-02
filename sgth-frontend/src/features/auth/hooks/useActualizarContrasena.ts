import { useMutation } from '@tanstack/react-query'
import { authService } from '../services/authService'
import type { ActualizarContrasenaPayload } from '../schemas/cambiarPassword.schema'
import { notificar } from '@/components/ui'

/**
 * Cambio de contraseña voluntario, desde el menú de usuario.
 *
 * Los errores por campo —la contraseña actual que no coincide, la cédula
 * dentro de la nueva— los pinta el formulario; aquí solo se notifica lo demás.
 */
export function useActualizarContrasena() {
  return useMutation({
    mutationFn: (data: ActualizarContrasenaPayload) => authService.actualizarContrasena(data),
    onSuccess: () => {
      notificar.exito(
        'Contraseña actualizada',
        'Se cerró la sesión en los demás equipos donde estaba abierta.',
      )
    },
    onError: notificar.alFallarSalvoCampos('No se pudo cambiar la contraseña'),
  })
}
