'use client'

import { use } from 'react'
import { CuestionarioPsicosocialPublico } from '@/features/sso/components/CuestionarioPsicosocialPublico'

interface Props {
  params: Promise<{ codigo: string }>
}

export default function PsicosocialPublicoPage({ params }: Props) {
  const { codigo } = use(params)
  return <CuestionarioPsicosocialPublico codigo={codigo} />
}
