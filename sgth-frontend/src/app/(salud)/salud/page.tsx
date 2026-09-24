import type { Metadata } from 'next'
import { SaludHomeView } from './SaludHomeView'

export const metadata: Metadata = {
  title: 'Dispensario Médico',
  description: 'Sistema de Salud Ambulatoria — GADPE',
}

export default function SaludHomePage() {
  return <SaludHomeView />
}
