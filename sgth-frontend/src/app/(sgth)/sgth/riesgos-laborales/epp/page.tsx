import type { Metadata } from 'next'
import { EquiposProteccionView } from './EquiposProteccionView'

export const metadata: Metadata = {
  title: 'Equipos de Protección Personal',
  description: 'Catálogo de equipos y el EPP que requiere cada puesto',
}

export default function EquiposProteccionPage() {
  return <EquiposProteccionView />
}
