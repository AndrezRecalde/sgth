import type { Metadata } from 'next'
import { CampaniaView } from './CampaniaView'

export const metadata: Metadata = {
  title: 'Campaña de preventivo',
  description: 'Los equipos de una campaña de mantenimiento preventivo: lo hecho, lo omitido y lo pendiente',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function CampaniaPage({ params }: Props) {
  const { id } = await params

  return <CampaniaView id={Number(id)} />
}
