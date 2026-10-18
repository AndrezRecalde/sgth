import type { Metadata } from 'next'
import { DetalleComunicadoView } from './DetalleComunicadoView'

export const metadata: Metadata = {
  title: 'Detalle de la publicación',
  description: 'Vista previa, destinatarios, fechas e historial de una noticia o un comunicado',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function DetalleComunicadoPage({ params }: Props) {
  const { id } = await params

  return <DetalleComunicadoView id={id} />
}
