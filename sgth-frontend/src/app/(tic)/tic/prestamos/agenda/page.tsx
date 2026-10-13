import type { Metadata } from 'next'
import { AgendaPrestamosView } from './AgendaPrestamosView'

export const metadata: Metadata = {
  title: 'Agenda de préstamos',
  description: 'Ocupación de los equipos de préstamo de TIC por día y por semana',
}

export default function AgendaPrestamosPage() {
  return <AgendaPrestamosView />
}
