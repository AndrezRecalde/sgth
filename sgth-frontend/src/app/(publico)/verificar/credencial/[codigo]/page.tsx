import type { Metadata } from 'next'
import { VerificacionCredencialView } from '@/features/comunicacion/components/VerificacionCredencialView'

export const metadata: Metadata = {
  title: 'Verificación de credencial',
  description: 'Compruebe que una credencial del GAD Provincial de Esmeraldas es auténtica y está vigente.',
  // Sin indexar, como la del certificado: cada dirección es la de una tarjeta.
  robots: { index: false, follow: false },
}

interface Props {
  params: Promise<{ codigo: string }>
}

export default async function VerificarCredencialPage({ params }: Props) {
  const { codigo } = await params

  return <VerificacionCredencialView codigo={codigo} />
}
