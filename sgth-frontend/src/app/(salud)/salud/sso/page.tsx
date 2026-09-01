import type { Metadata } from 'next'
import { SsoView } from './SsoView'

export const metadata: Metadata = {
  title: 'Salud Ocupacional',
  description: 'Fichas FEMO y solicitudes de Talento Humano — GAD Provincial de Esmeraldas',
}

export default function SsoPage() {
  return <SsoView />
}
