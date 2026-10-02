import type { Metadata } from 'next'
import { CertificacionesMedicasView } from './CertificacionesMedicasView'

export const metadata: Metadata = {
  title: 'Certificaciones médicas',
  description: 'Cobertura de las evaluaciones médicas ocupacionales de la plantilla',
}

export default function CertificacionesMedicasPage() {
  return <CertificacionesMedicasView />
}
