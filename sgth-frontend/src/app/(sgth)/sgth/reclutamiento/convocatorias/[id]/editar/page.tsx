import type { Metadata } from 'next'
import { EditarConvocatoriaView } from './EditarConvocatoriaView'

export const metadata: Metadata = {
  title: 'Editar convocatoria',
  description: 'Corregir una convocatoria antes de publicarla',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function EditarConvocatoriaPage({ params }: Props) {
  const { id } = await params

  return <EditarConvocatoriaView id={id} />
}
