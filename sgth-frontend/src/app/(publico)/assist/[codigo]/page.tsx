'use client'

import { use } from 'react'
import { AssistCuestionarioPublico } from '@/features/sso/components/AssistCuestionarioPublico'

interface Props {
  params: Promise<{ codigo: string }>
}

export default function AssistPublicoPage({ params }: Props) {
  const { codigo } = use(params)
  return <AssistCuestionarioPublico codigo={codigo} />
}
