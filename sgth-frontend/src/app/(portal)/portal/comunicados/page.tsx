import type { Metadata } from 'next'
import { MisComunicadosView } from '@/features/portal/components/MisComunicadosView'

export const metadata: Metadata = {
  title: 'Noticias y comunicados',
  description: 'Lo que Talento Humano publica para usted',
}

interface Props {
  searchParams: Promise<{ lectura?: string | string[] }>
}

/** `?lectura=por_confirmar` la abre en lo pendiente: es a donde lleva el aviso del inicio. */
export default async function MisComunicadosPage({ searchParams }: Props) {
  const { lectura } = await searchParams

  return <MisComunicadosView lecturaInicial={lectura === 'por_confirmar' || lectura === 'sin_leer' ? lectura : undefined} />
}
