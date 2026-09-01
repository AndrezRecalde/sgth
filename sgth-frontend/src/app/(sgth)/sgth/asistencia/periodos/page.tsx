import type { Metadata } from 'next'
import { PeriodosVacacionesView } from './PeriodosVacacionesView'

export const metadata: Metadata = {
  title: 'Períodos de vacaciones',
  description: 'Períodos y saldos de vacaciones del GAD Provincial de Esmeraldas',
}

export default function PeriodosVacacionesPage() {
  return <PeriodosVacacionesView />
}
