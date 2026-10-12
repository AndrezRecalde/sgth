import type { Metadata } from 'next'
import { MisActividadesView } from '@/features/portal/components/MisActividadesView'

export const metadata: Metadata = {
  title: 'Mis actividades',
  description: 'Las actividades laborales del servidor por fecha, sus filtros y el informe en PDF',
}

export default function MisActividadesPage() {
  return <MisActividadesView />
}
