import type { Metadata } from 'next'
import { MisMarcacionesView } from '@/features/asistencia/components/MisMarcacionesView'

export const metadata: Metadata = {
  title: 'Mis marcaciones',
  description: 'Los registros del reloj biométrico del servidor por rango de fechas',
}

export default function MisMarcacionesPage() {
  return <MisMarcacionesView />
}
