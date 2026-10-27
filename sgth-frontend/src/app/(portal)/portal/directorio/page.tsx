import type { Metadata } from 'next'
import { DirectorioView } from './DirectorioView'

export const metadata: Metadata = {
  title: 'Directorio telefónico',
  description: 'Extensiones telefónicas por unidad administrativa del GAD Provincial de Esmeraldas',
}

export default function DirectorioPage() {
  return <DirectorioView />
}
