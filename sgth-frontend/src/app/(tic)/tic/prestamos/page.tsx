import type { Metadata } from 'next'
import { BandejaPrestamosView } from './BandejaPrestamosView'

export const metadata: Metadata = {
  title: 'Préstamos',
  description: 'Bandeja de préstamos de equipos de TIC: aprobar, entregar y recibir',
}

export default function PrestamosPage() {
  return <BandejaPrestamosView />
}
