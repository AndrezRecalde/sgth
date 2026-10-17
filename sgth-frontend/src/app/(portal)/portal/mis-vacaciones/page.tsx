import type { Metadata } from 'next'
import { MisVacacionesView } from '@/features/asistencia/components/MisVacacionesView'

export const metadata: Metadata = {
  title: 'Mis vacaciones',
  description: 'El saldo, los períodos y las solicitudes de vacaciones del servidor',
}

export default function MisVacacionesPage() {
  return <MisVacacionesView />
}
