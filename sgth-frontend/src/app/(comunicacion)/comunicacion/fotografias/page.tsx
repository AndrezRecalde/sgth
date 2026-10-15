import type { Metadata } from 'next'
import { FotografiasView } from './FotografiasView'

export const metadata: Metadata = {
  title: 'Fotografías',
  description: 'Las fotografías de los servidores, para su credencial',
}

export default function FotografiasPage() {
  return <FotografiasView />
}
