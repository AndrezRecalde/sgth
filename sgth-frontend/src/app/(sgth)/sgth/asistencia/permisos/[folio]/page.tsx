import type { Metadata } from 'next'
import { PermisoPorFolioView } from './PermisoPorFolioView'

export const metadata: Metadata = {
  title: 'Permiso',
  description: 'Detalle de un permiso de ausencia del GAD Provincial de Esmeraldas',
}

interface Props {
  params: Promise<{ folio: string }>
}

export default async function PermisoPorFolioPage({ params }: Props) {
  const { folio } = await params

  return <PermisoPorFolioView folio={decodeURIComponent(folio)} />
}
