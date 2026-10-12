import type { Metadata } from 'next'
import { MisDocumentosLaboralesView } from '@/features/portal/components/MisDocumentosLaboralesView'

export const metadata: Metadata = {
  title: 'Mis documentos laborales',
  description: 'Las acciones de personal notificadas al servidor, su documento y su acuse de recibo',
}

export default function MisDocumentosLaboralesPage() {
  return <MisDocumentosLaboralesView />
}
