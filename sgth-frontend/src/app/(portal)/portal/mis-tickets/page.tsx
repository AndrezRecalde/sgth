import type { Metadata } from 'next'
import { MisTicketsView } from './MisTicketsView'

export const metadata: Metadata = {
  title: 'Mis tickets',
  description: 'Sus pedidos de ayuda a la Unidad de TIC',
}

export default function MisTicketsPage() {
  return <MisTicketsView />
}
