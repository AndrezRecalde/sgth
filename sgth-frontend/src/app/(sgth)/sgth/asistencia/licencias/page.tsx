import type { Metadata } from 'next'
import { LicenciasView } from './LicenciasView'

export const metadata: Metadata = {
  title: 'Licencias',
  description: 'Licencias con remuneración del personal del GAD Provincial de Esmeraldas',
}

export default function LicenciasPage() {
  return <LicenciasView />
}
