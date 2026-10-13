import type { Metadata } from 'next'
import { MisEquiposView } from './MisEquiposView'

export const metadata: Metadata = {
  title: 'Mis equipos',
  description: 'Los equipos informáticos que tiene a su cargo o usa',
}

export default function MisEquiposPage() {
  return <MisEquiposView />
}
