import type { Metadata } from 'next'
import { ExamenesView } from './ExamenesView'

export const metadata: Metadata = {
  title: 'Exámenes',
  description: 'Perfiles y catálogo de exámenes del Dispensario',
}

export default function ExamenesPage() {
  return <ExamenesView />
}
