import type { Metadata } from 'next'
import { ExpressPageView } from './ExpressPageView'

export const metadata: Metadata = {
  title: 'Reclutamiento Express',
  description: 'Contenedores permanentes de reclutamiento por modalidad del GAD Provincial de Esmeraldas',
}

export default function ReclutamientoExpressPage() {
  return <ExpressPageView />
}
