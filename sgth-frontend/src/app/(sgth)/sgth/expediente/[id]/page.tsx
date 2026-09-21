import type { Metadata } from 'next'
import { ServidorDetalleView } from './ServidorDetalleView'

export const metadata: Metadata = {
  title: 'Expediente del servidor',
  description: 'Ficha, vínculo laboral y documentos de un servidor del GAD Provincial de Esmeraldas',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function ExpedienteServidorPage({ params }: Props) {
  const { id } = await params

  return <ServidorDetalleView id={id} />
}
