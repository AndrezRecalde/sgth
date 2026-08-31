import type { Metadata } from 'next'
import { DespachoView } from './DespachoView'

export const metadata: Metadata = {
  title: 'Despacho de recetas',
  description: 'Entrega de medicamentos recetados por el Dispensario Médico del GAD Provincial de Esmeraldas',
}

export default function DespachoPage() {
  return <DespachoView />
}
