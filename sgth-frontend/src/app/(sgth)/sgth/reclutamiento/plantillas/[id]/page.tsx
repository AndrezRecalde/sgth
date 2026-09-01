import type { Metadata } from 'next'
import { DetallePlantillaView } from './DetallePlantillaView'

export const metadata: Metadata = {
  title: 'Detalle de plantilla',
  description: 'Criterios de evaluación de la plantilla',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function DetallePlantillaPage({ params }: Props) {
  const { id } = await params

  return <DetallePlantillaView id={id} />
}
