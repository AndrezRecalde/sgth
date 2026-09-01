import type { Metadata } from 'next'
import { FarmaciaView } from './FarmaciaView'

export const metadata: Metadata = {
  title: 'Farmacia',
  description: 'Gestión del inventario de medicinas',
}

export default function FarmaciaPage() {
  return <FarmaciaView />
}
