import type { Metadata } from 'next'
import { ExpedienteView } from './ExpedienteView'

export const metadata: Metadata = {
  title: 'Expediente Digital',
  description: 'Gestión de expedientes de servidores públicos del GAD Provincial de Esmeraldas',
}

export default function ExpedientePage() {
  return <ExpedienteView />
}
