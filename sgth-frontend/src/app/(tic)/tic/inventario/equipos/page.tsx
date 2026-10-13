import type { Metadata } from 'next'
import { EquiposView } from './EquiposView'

export const metadata: Metadata = {
  title: 'Equipos',
  description: 'Inventario de equipos informáticos de la Unidad de TIC',
}

export default function EquiposPage() {
  return <EquiposView />
}
