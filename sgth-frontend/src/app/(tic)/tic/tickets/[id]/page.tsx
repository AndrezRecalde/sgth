import type { Metadata } from 'next'
import { TicketTicView } from './TicketTicView'

export const metadata: Metadata = {
  title: 'Ticket',
  description: 'Un ticket de la mesa de ayuda: conversación, notas internas, SLA y trámite',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function TicketPage({ params }: Props) {
  const { id } = await params

  return <TicketTicView id={Number(id)} />
}
