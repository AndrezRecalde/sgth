import { permanentRedirect } from 'next/navigation'
import { ROUTES } from '@/config/routes'

/**
 * El directorio telefónico salió del SGTH: lo consulta todo el personal desde
 * el Portal y lo mantiene TIC (docs/plan-roles-y-accesos.md, PR 11). Esta ruta
 * queda para no romper un marcador o un enlace guardado.
 */
export default function DirectorioMovidoPage() {
  permanentRedirect(ROUTES.PORTAL.DIRECTORIO)
}
