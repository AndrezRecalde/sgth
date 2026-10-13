import type { Metadata } from 'next'
import { MiTicketView } from './MiTicketView'

export const metadata: Metadata = {
  title: 'Ticket',
  description: 'Un pedido de ayuda a TIC: la conversación, la solución y su confirmación',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function MiTicketPage({ params }: Props) {
  const { id } = await params

  return <MiTicketView id={Number(id)} />
}
