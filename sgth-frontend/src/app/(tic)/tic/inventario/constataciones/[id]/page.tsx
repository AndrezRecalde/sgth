import type { Metadata } from 'next'
import { ConstatacionView } from './ConstatacionView'

export const metadata: Metadata = {
  title: 'Constatación',
  description: 'Escanear las etiquetas de los equipos de una ubicación y cerrar la constatación',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function ConstatacionPage({ params }: Props) {
  const { id } = await params

  return <ConstatacionView id={Number(id)} />
}
