import type { Metadata } from 'next'
import { TicHomeView } from './TicHomeView'

export const metadata: Metadata = {
  title: 'Tecnologías',
  description: 'Mesa de ayuda, inventario y préstamo de equipos informáticos',
}

export default function TicHomePage() {
  return <TicHomeView />
}
