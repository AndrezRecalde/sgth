import { permanentRedirect } from 'next/navigation'
import { ROUTES } from '@/config/routes'

/**
 * Usuarios se mudó a Tecnologías › Administración: las cuentas de acceso las
 * lleva TI, no Talento Humano (docs/plan-roles-y-accesos.md, PR 10). Esta
 * ruta queda para no romper un marcador o un enlace guardado.
 */
export default function UsuariosMovidaPage() {
  permanentRedirect(ROUTES.TIC.USUARIOS)
}
