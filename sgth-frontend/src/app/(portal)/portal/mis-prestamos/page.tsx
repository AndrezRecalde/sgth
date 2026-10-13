import type { Metadata } from 'next'
import { MisPrestamosView } from './MisPrestamosView'

export const metadata: Metadata = {
  title: 'Mis préstamos',
  description: 'Sus solicitudes y préstamos de equipos de TIC',
}

export default function MisPrestamosPage() {
  return <MisPrestamosView />
}
