import type { Metadata } from 'next'
import { EditarComunicadoView } from './EditarComunicadoView'

export const metadata: Metadata = {
  title: 'Editar borrador',
  description: 'Texto, destinatarios, fechas y documentos de una publicación',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function EditarComunicadoPage({ params }: Props) {
  const { id } = await params

  return <EditarComunicadoView id={id} />
}
