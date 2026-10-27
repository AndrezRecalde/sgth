import type { Metadata } from 'next'
import { DirectorioTicView } from './DirectorioTicView'

export const metadata: Metadata = {
  title: 'Directorio telefónico',
  description: 'Las extensiones telefónicas de cada unidad administrativa, activas e inactivas',
}

export default function DirectorioTicPage() {
  return <DirectorioTicView />
}
