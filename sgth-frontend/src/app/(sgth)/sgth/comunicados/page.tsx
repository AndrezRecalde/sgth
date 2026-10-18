import type { Metadata } from 'next'
import { ComunicadosView } from './ComunicadosView'

export const metadata: Metadata = {
  title: 'Noticias y comunicados',
  description: 'Lo que Talento Humano publica en el Portal del servidor',
}

export default function ComunicadosPage() {
  return <ComunicadosView />
}
