import type { Metadata } from 'next'
import { AccidentesTrabajoView } from './AccidentesTrabajoView'

export const metadata: Metadata = {
  title: 'Accidentes de Trabajo',
  description: 'Registro e investigación de accidentes e incidentes laborales',
}

export default function AccidentesTrabajoPage() {
  return <AccidentesTrabajoView />
}
