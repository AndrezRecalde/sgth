import type { Metadata } from 'next'
import { PuestosCargosView } from './PuestosCargosView'

export const metadata: Metadata = {
  title: 'Puestos y cargos',
  description: 'Catálogo de puestos, cargos y grupos ocupacionales del GAD Provincial de Esmeraldas',
}

export default function PuestosCargosPage() {
  return <PuestosCargosView />
}
