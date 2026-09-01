import type { Metadata } from 'next'
import { CuestionarioPsicosocialPublico } from '@/features/sso/components/CuestionarioPsicosocialPublico'

export const metadata: Metadata = {
  title: 'Cuestionario psicosocial',
  description: 'Evaluación de factores de riesgo psicosocial',
}

interface Props {
  params: Promise<{ codigo: string }>
}

export default async function PsicosocialPublicoPage({ params }: Props) {
  const { codigo } = await params

  return <CuestionarioPsicosocialPublico codigo={codigo} />
}
