import type { Metadata } from 'next'
import { AtencionTurnoView } from '@/features/dispensario/components/AtencionTurnoView'

export const metadata: Metadata = {
  title: 'Odontología',
  description: 'Ficha de atención odontológica',
}

interface Props {
  params: Promise<{ folio: string }>
}

export default async function OdontologiaDetallePage({ params }: Props) {
  const { folio } = await params

  return <AtencionTurnoView folio={folio} especialidad="odontologica" />
}
