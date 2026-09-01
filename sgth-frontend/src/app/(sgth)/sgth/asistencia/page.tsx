import type { Metadata } from 'next'
import { MarcacionesView } from './MarcacionesView'

export const metadata: Metadata = {
  title: 'Marcaciones',
  description: 'Registros del reloj biométrico del GAD Provincial de Esmeraldas',
}

export default function MarcacionesPage() {
  return <MarcacionesView />
}
