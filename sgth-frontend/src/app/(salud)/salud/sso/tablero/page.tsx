import type { Metadata } from 'next'
import { TableroSsoView } from './TableroSsoView'

export const metadata: Metadata = {
  title: 'Tablero de salud ocupacional',
  description: 'Evaluaciones médicas ocupacionales en cifras',
}

export default function TableroSsoPage() {
  return <TableroSsoView />
}
