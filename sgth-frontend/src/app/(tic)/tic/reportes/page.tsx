import type { Metadata } from 'next'
import { ReportesTicView } from './ReportesTicView'

export const metadata: Metadata = {
  title: 'Reportes de Tecnologías',
  description: 'Inventario por unidad o ubicación, obsolescencia, mesa de ayuda por técnico y categoría, y satisfacción',
}

export default function ReportesTicPage() {
  return <ReportesTicView />
}
