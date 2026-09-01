import type { Metadata } from 'next'
import { AtencionTurnoView } from '@/features/dispensario/components/AtencionTurnoView'

export const metadata: Metadata = {
  title: 'Consulta',
  description: 'Ficha de atención médica',
}

interface Props {
  params: Promise<{ folio: string }>
}

export default async function ConsultaDetallePage({ params }: Props) {
  const { folio } = await params

  return <AtencionTurnoView folio={folio} especialidad="medica" />
}
