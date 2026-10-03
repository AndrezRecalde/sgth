import type { Metadata } from 'next'
import { SaludHomeView } from './SaludHomeView'

export const metadata: Metadata = {
  title: 'Dispensario Médico',
  description: 'Tablero del Dispensario Médico',
}

export default function SaludHomePage() {
  return <SaludHomeView />
}
