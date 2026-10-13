import type { Metadata } from 'next'
import { ReporteUsoView } from './ReporteUsoView'

export const metadata: Metadata = {
  title: 'Uso de los equipos de préstamo',
  description: 'Préstamos, horas fuera, devoluciones tarde y reservas no retiradas por equipo',
}

export default function ReporteUsoPage() {
  return <ReporteUsoView />
}
