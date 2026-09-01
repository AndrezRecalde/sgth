import type { Metadata } from 'next'
import { NuevaFemoView } from './NuevaFemoView'

export const metadata: Metadata = {
  title: 'Nueva ficha FEMO',
  description: 'Ficha de evaluación médica ocupacional',
}

interface Props {
  params: Promise<{ solicitudId: string }>
}

export default async function NuevaFemoPage({ params }: Props) {
  const { solicitudId } = await params

  return <NuevaFemoView solicitudId={solicitudId} />
}
