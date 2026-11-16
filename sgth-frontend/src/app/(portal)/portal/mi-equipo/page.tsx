import type { Metadata } from 'next'
import { MiEquipoView } from '@/features/asistencia/components/MiEquipoView'

export const metadata: Metadata = {
  title: 'Mi equipo',
  description: 'Los permisos y vacaciones de la unidad que dirige el servidor',
}

export default function MiEquipoPage() {
  return <MiEquipoView />
}
