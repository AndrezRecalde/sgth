import type { Metadata } from 'next'
import { CampaniasView } from './CampaniasView'

export const metadata: Metadata = {
  title: 'Plan preventivo',
  description: 'Campañas de mantenimiento preventivo por piso o sede, con su técnico y su avance',
}

export default function CampaniasPage() {
  return <CampaniasView />
}
