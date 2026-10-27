import type { Metadata } from 'next'
import { RequierePermiso } from '@/components/layout/RequierePermiso'
import { ROUTES } from '@/config/routes'
import { EntregarConsumiblesView } from './EntregarConsumiblesView'

export const metadata: Metadata = {
  title: 'Entregar consumibles',
  description: 'Entregar tóner, tinta o repuestos a un equipo, con el lector QR desde el celular',
}

interface Props {
  searchParams: Promise<{ bien?: string }>
}

export default async function EntregarConsumiblesPage({ searchParams }: Props) {
  const { bien } = await searchParams

  return (
    // El listado lo abre también quien solo consulta el inventario.
    <RequierePermiso permiso="gestionar-inventario-ti" volverA={ROUTES.TIC.CONSUMIBLES}>
      <EntregarConsumiblesView bienId={bien ? Number(bien) : null} />
    </RequierePermiso>
  )
}
