import type { Metadata } from 'next'
import { EstructuraView } from './EstructuraView'

export const metadata: Metadata = {
  title: 'Estructura Organizacional',
  description: 'Gestión del organigrama institucional y puestos del GAD Provincial de Esmeraldas',
}

export default function EstructuraPage() {
  return <EstructuraView />
}
