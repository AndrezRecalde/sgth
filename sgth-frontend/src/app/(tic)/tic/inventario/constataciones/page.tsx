import type { Metadata } from 'next'
import { ConstatacionesView } from './ConstatacionesView'

export const metadata: Metadata = {
  title: 'Constatación física',
  description: 'Recorridos de TIC para comprobar con el QR que los equipos están donde dice el inventario',
}

export default function ConstatacionesPage() {
  return <ConstatacionesView />
}
