import type { Metadata } from 'next'
import { PermisosView } from './PermisosView'

export const metadata: Metadata = {
  title: 'Permisos',
  description: 'Solicitudes de permiso del personal del GAD Provincial de Esmeraldas',
}

export default function PermisosPage() {
  return <PermisosView />
}
