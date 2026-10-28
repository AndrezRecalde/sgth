import type { Metadata } from 'next'
import { RequierePermiso } from '@/components/layout/RequierePermiso'
import { ROUTES } from '@/config/routes'
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

  return (
    // La ficha la abre también quien solo consulta el inventario.
    <RequierePermiso permiso="gestionar-inventario-ti" volverA={ROUTES.TIC.EQUIPO(Number(id))} textoVolver="Volver a la ficha">
      <EditarEquipoView id={Number(id)} />
    </RequierePermiso>
  )
}
