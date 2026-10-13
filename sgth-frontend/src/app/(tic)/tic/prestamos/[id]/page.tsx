import type { Metadata } from 'next'
import { PrestamoTicView } from './PrestamoTicView'

export const metadata: Metadata = {
  title: 'Préstamo',
  description: 'Detalle de un préstamo de equipos de TIC: equipos, horas previstas y reales, y su trámite',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function PrestamoPage({ params }: Props) {
  const { id } = await params

  return <PrestamoTicView id={Number(id)} />
}
