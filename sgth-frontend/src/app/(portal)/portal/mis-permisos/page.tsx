import type { Metadata } from 'next'
import { MisPermisosView } from '@/features/asistencia/components/MisPermisosView'

export const metadata: Metadata = {
  title: 'Mis permisos',
  description: 'Los permisos de ausencia del servidor: estado, plazo del respaldo y motivo',
}

export default function MisPermisosPage() {
  return <MisPermisosView />
}
