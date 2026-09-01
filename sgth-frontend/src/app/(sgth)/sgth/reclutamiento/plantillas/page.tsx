import type { Metadata } from 'next'
import { PlantillasView } from './PlantillasView'

export const metadata: Metadata = {
  title: 'Plantillas de evaluación',
  description: 'Configuración de criterios reutilizables para convocatorias',
}

export default function PlantillasPage() {
  return <PlantillasView />
}
