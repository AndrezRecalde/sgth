import type { Metadata } from 'next'
import { FemoDetalleView } from './FemoDetalleView'

export const metadata: Metadata = {
  title: 'Ficha FEMO',
  description: 'Ficha de evaluación médica ocupacional',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function FemoDetallePage({ params }: Props) {
  const { id } = await params

  return <FemoDetalleView id={id} />
}
