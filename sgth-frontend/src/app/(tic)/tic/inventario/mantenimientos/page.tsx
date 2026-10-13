import type { Metadata } from 'next'
import { MantenimientosView } from './MantenimientosView'

export const metadata: Metadata = {
  title: 'Mantenimientos',
  description: 'Mantenimientos de los equipos informáticos: preventivos, correctivos y repotenciaciones',
}

export default function MantenimientosPage() {
  return <MantenimientosView />
}
