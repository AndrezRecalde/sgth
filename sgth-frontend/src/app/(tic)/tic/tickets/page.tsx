import type { Metadata } from 'next'
import { BandejaTicketsView } from './BandejaTicketsView'

export const metadata: Metadata = {
  title: 'Mesa de ayuda',
  description: 'Bandeja de tickets de la mesa de ayuda de TIC',
}

export default function TicketsPage() {
  return <BandejaTicketsView />
}
