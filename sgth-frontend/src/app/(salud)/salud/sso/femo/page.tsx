import type { Metadata } from 'next'
import { FemoView } from './FemoView'

export const metadata: Metadata = {
  title: 'Fichas FEMO',
  description: 'FEMO — Evaluaciones médicas ocupacionales',
}

export default function FemoPage() {
  return <FemoView />
}
