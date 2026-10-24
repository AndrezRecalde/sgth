import type { Metadata } from 'next'
import { MisExamenesView } from '@/features/portal/components/MisExamenesView'

export const metadata: Metadata = {
  title: 'Mis exámenes médicos',
  description: 'Las órdenes de exámenes del Dispensario, su impreso y el envío de resultados',
}

export default function MisExamenesPage() {
  return <MisExamenesView />
}
