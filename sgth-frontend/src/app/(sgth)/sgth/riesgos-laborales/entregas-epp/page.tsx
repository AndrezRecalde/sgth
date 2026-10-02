import type { Metadata } from 'next'
import { EntregasEppView } from './EntregasEppView'

export const metadata: Metadata = {
  title: 'Entregas de EPP',
  description: 'Bitácora de entregas, devoluciones y reposiciones de equipo de protección',
}

export default function EntregasEppPage() {
  return <EntregasEppView />
}
