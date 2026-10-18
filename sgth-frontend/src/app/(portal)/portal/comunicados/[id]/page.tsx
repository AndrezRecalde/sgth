import type { Metadata } from 'next'
import { ComunicadoPortalView } from '@/features/portal/components/ComunicadoPortalView'

export const metadata: Metadata = {
  title: 'Noticias y comunicados',
  description: 'Una publicación de Talento Humano',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function ComunicadoPortalPage({ params }: Props) {
  const { id } = await params

  return <ComunicadoPortalView id={id} />
}
