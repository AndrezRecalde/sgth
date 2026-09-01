import type { Metadata } from 'next'
import { MarcacionOnlineView } from './MarcacionOnlineView'

export const metadata: Metadata = {
  title: 'Marcación online',
  description: 'Registro de entrada y salida desde el navegador con ubicación',
}

export default function MarcacionOnlinePage() {
  return <MarcacionOnlineView />
}
