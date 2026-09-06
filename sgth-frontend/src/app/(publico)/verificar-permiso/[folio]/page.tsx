import type { Metadata } from 'next'
import { VerificacionPermisoPublica } from '@/features/asistencia/components/VerificacionPermisoPublica'

export const metadata: Metadata = {
  title: 'Verificación de permiso',
  description: 'Verificación pública de un permiso emitido por el GAD Provincial de Esmeraldas',
}

interface Props {
  params: Promise<{ folio: string }>
}

export default async function VerificarPermisoPage({ params }: Props) {
  const { folio } = await params

  return <VerificacionPermisoPublica folio={decodeURIComponent(folio)} />
}
