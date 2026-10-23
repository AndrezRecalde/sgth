import type { Metadata } from 'next'
import { ExamenesView } from './ExamenesView'

export const metadata: Metadata = {
  title: 'Exámenes',
  description: 'Órdenes que esperan resultados, perfiles y catálogo de exámenes del Dispensario',
}

export default function ExamenesPage() {
  return <ExamenesView />
}
