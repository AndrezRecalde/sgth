import type { Metadata } from 'next'
import { SgthHomeView } from './SgthHomeView'

export const metadata: Metadata = {
  title: 'Talento Humano',
  description:
    'Panel principal del subsistema de Gestión de Talento Humano del GAD Provincial de Esmeraldas',
}

export default function SgthHomePage() {
  return <SgthHomeView />
}
