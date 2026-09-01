import type { Metadata } from 'next'
import { AssistCuestionarioPublico } from '@/features/sso/components/AssistCuestionarioPublico'

export const metadata: Metadata = {
  title: 'Cuestionario ASSIST',
  description: 'Cuestionario de tamizaje de consumo de sustancias',
}

interface Props {
  params: Promise<{ codigo: string }>
}

export default async function AssistPublicoPage({ params }: Props) {
  const { codigo } = await params

  return <AssistCuestionarioPublico codigo={codigo} />
}
