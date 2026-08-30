import type { Metadata } from 'next'
import { PlantillaView } from './PlantillaView'

export const metadata: Metadata = {
  title: 'Plantilla institucional',
  description: 'Plazas, ocupación y personal por modalidad del GAD Provincial de Esmeraldas',
}

export default function PlantillaPage() {
  return <PlantillaView />
}
