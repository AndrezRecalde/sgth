import type { Metadata } from 'next'
import { VerificacionCertificadoView } from '@/features/expediente/components/VerificacionCertificadoView'

export const metadata: Metadata = {
  title: 'Verificación de certificado',
  description:
    'Compruebe la autenticidad de un certificado emitido por el GAD Provincial de Esmeraldas.',
  // Sin indexar, al contrario que el organigrama: cada dirección lleva el
  // código de un certificado concreto y no hay motivo para que un buscador
  // recorra los que se hayan compartido por ahí.
  robots: { index: false, follow: false },
}

interface Props {
  params: Promise<{ codigo: string }>
}

export default async function VerificarCertificadoPage({ params }: Props) {
  const { codigo } = await params

  return <VerificacionCertificadoView codigo={codigo} />
}
