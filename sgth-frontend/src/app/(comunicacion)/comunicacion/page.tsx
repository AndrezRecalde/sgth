import type { Metadata } from 'next'
import { ComunicacionHomeView } from './ComunicacionHomeView'

export const metadata: Metadata = {
  title: 'Comunicación Social',
  description: 'Fotografías de los servidores, credenciales e identidad institucional',
}

export default function ComunicacionHomePage() {
  return <ComunicacionHomeView />
}
