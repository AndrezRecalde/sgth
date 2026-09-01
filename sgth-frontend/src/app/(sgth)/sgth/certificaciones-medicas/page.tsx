import type { Metadata } from 'next'
import { CertificacionesMedicasView } from './CertificacionesMedicasView'

export const metadata: Metadata = {
  title: 'Certificaciones médicas',
  description: 'Seguimiento de las solicitudes enviadas al Dispensario Médico',
}

export default function CertificacionesMedicasPage() {
  return <CertificacionesMedicasView />
}
