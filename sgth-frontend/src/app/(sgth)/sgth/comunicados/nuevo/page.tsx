import type { Metadata } from 'next'
import { NuevoComunicadoView } from './NuevoComunicadoView'

export const metadata: Metadata = {
  title: 'Nueva publicación',
  description: 'Una noticia o un comunicado de Talento Humano para el Portal',
}

export default function NuevoComunicadoPage() {
  return <NuevoComunicadoView />
}
