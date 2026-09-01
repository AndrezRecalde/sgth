import type { Metadata } from 'next'
import { AdquisicionesView } from './AdquisicionesView'

export const metadata: Metadata = {
  title: 'Adquisiciones de Medicamentos',
  description: 'Registro de compras y donaciones con respaldo documental',
}

export default function AdquisicionesPage() {
  return <AdquisicionesView />
}
