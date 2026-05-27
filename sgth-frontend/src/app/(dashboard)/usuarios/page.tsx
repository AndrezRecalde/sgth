import type { Metadata } from 'next'
import { UsuariosView } from './UsuariosView'

export const metadata: Metadata = {
  title: 'Gestión de Usuarios',
  description: 'Administración de accesos y roles del sistema',
}

export default function UsuariosPage() {
  return <UsuariosView />
}
