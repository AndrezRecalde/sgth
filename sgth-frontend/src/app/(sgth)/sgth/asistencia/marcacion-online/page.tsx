import { permanentRedirect } from 'next/navigation'
import { ROUTES } from '@/config/routes'

/**
 * La marcación en línea se mudó al portal: es autoservicio, y el rol
 * `servidor` no entra al subsistema SGTH. Esta ruta queda para no romper un
 * marcador o un enlace guardado.
 */
export default function MarcacionOnlineMovidaPage() {
  permanentRedirect(ROUTES.PORTAL.MARCACION_ONLINE)
}
