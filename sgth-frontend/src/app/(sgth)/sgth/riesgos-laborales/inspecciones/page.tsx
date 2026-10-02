import type { Metadata } from 'next'
import { InspeccionesSsoView } from './InspeccionesSsoView'

export const metadata: Metadata = {
  title: 'Inspecciones de Seguridad',
  description: 'Inspecciones por unidad administrativa, con sus hallazgos y recomendaciones',
}

export default function InspeccionesSsoPage() {
  return <InspeccionesSsoView />
}
