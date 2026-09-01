import type { Metadata } from 'next'
import { NuevaConvocatoriaView } from './NuevaConvocatoriaView'

export const metadata: Metadata = {
  title: 'Nueva convocatoria',
  description: 'Concurso de méritos y oposición para un puesto del organigrama',
}

export default function NuevaConvocatoriaPage() {
  return <NuevaConvocatoriaView />
}
