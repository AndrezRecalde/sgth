import type { Metadata } from 'next'
import { CapacitacionesSsoView } from './CapacitacionesSsoView'

export const metadata: Metadata = {
  title: 'Capacitaciones en SSO',
  description: 'Capacitaciones del período, de donde salen las horas del índice proactivo',
}

export default function CapacitacionesSsoPage() {
  return <CapacitacionesSsoView />
}
