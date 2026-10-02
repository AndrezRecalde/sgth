import { useState } from 'react'
import { authService } from '../services/authService'
import { useAuth } from '@/hooks/useAuth'
import { ROUTES } from '@/config/routes'

/**
 * Cierra la sesión en el servidor y luego en el navegador.
 *
 * Primero el servidor: revoca el token y deja al médico como no disponible en
 * el Dispensario. Si la llamada falla —sin red, token ya caducado— se sale
 * igual: quedarse dentro no es una opción.
 *
 * La recarga completa es a propósito: descarta la caché de TanStack Query,
 * que guarda datos del servidor del usuario que sale.
 */
export function useCerrarSesion() {
  const { clearAuth } = useAuth()
  const [cerrando, setCerrando] = useState(false)

  const cerrarSesion = async () => {
    setCerrando(true)

    try {
      await authService.logout()
    } catch {
      // Sin nada que hacer: la sesión local se cierra de todos modos.
    }

    clearAuth()
    window.location.href = `${ROUTES.AUTH.LOGIN}?logout=true`
  }

  return { cerrarSesion, cerrando }
}
