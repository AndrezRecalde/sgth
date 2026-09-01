import type { Metadata } from 'next'
import { ConvocatoriasView } from './ConvocatoriasView'

export const metadata: Metadata = {
  title: 'Convocatorias',
  description: 'Gestión de procesos de selección e incorporación',
}

export default function ConvocatoriasPage() {
  return <ConvocatoriasView />
}
