import type { Metadata } from 'next'
import { VacacionesView } from './VacacionesView'

export const metadata: Metadata = {
  title: 'Vacaciones',
  description: 'Solicitudes de vacaciones del personal del GAD Provincial de Esmeraldas',
}

export default function VacacionesPage() {
  return <VacacionesView />
}
