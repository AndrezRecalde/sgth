import type { Metadata } from 'next'
import { AccionesPersonalView } from './AccionesPersonalView'

export const metadata: Metadata = {
  title: 'Acciones de Personal',
  description: 'Bandeja y registro de acciones de personal del GAD Provincial de Esmeraldas',
}

export default function AccionesPersonalPage() {
  return <AccionesPersonalView />
}
