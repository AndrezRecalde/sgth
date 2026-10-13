import type { Metadata } from 'next'
import { EditarEquipoView } from './EditarEquipoView'

export const metadata: Metadata = {
  title: 'Editar equipo',
  description: 'Corregir la ficha de un equipo del inventario de TIC',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function EditarEquipoPage({ params }: Props) {
  const { id } = await params

  return <EditarEquipoView id={Number(id)} />
}
