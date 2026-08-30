import type { Metadata } from 'next'
import { PartidasView } from './PartidasView'

export const metadata: Metadata = {
  title: 'Partidas presupuestarias',
  description: 'Partidas presupuestarias del GAD Provincial de Esmeraldas',
}

export default function PartidasPage() {
  return <PartidasView />
}
