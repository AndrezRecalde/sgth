import type { Metadata } from 'next'
import { DetalleConvocatoriaView } from './DetalleConvocatoriaView'

export const metadata: Metadata = {
  title: 'Detalle de convocatoria',
  description: 'Fases, candidatos y resultados del concurso',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function DetalleConvocatoriaPage({ params }: Props) {
  const { id } = await params

  return <DetalleConvocatoriaView id={id} />
}
