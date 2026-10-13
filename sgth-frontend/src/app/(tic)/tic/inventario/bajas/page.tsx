import type { Metadata } from 'next'
import { BajasView } from './BajasView'

export const metadata: Metadata = {
  title: 'Bajas',
  description: 'Bajas de los equipos informáticos: informe técnico, aprobación y entrega a Activos Fijos',
}

export default function BajasPage() {
  return <BajasView />
}
