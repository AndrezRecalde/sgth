import type { Metadata } from 'next'
import { AusentismoView } from './AusentismoView'

export const metadata: Metadata = {
  title: 'Ausentismo por Enfermedad',
  description: 'Consolidado de permisos médicos del período, como indicador reactivo',
}

export default function AusentismoPage() {
  return <AusentismoView />
}
