import type { Metadata } from 'next'
import { IdentidadView } from './IdentidadView'

export const metadata: Metadata = {
  title: 'Identidad institucional',
  description: 'Los logos de la institución, con su vigencia, y los datos que salen en los documentos',
}

export default function IdentidadPage() {
  return <IdentidadView />
}
