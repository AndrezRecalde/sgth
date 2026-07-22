import type { Metadata } from 'next'
import { SubrogacionesView } from './SubrogacionesView'

export const metadata: Metadata = {
  title: 'Subrogaciones y Encargos',
  description: 'Administración de subrogaciones y encargos de puestos del GAD Provincial de Esmeraldas',
}

export default function SubrogacionesPage() {
  return <SubrogacionesView />
}
