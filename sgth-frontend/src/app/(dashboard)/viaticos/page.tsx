import { Metadata } from 'next'
import { ViaticoView } from '@/features/viaticos/components/ViaticoView'

export const metadata: Metadata = {
  title: 'Viáticos — SGTH GADPE',
}

export default function ViaticoPage() {
  return <ViaticoView />
}
