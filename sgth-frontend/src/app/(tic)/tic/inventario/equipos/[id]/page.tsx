import type { Metadata } from 'next'
import { EquipoDetalleView } from './EquipoDetalleView'

export const metadata: Metadata = {
  title: 'Ficha del equipo',
  description: 'Datos, custodia, mantenimientos y componentes de un equipo del inventario de TIC',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function EquipoDetallePage({ params }: Props) {
  const { id } = await params

  return <EquipoDetalleView id={Number(id)} />
}
