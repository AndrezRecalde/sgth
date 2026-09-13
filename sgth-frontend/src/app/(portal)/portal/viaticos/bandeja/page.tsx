import { Metadata } from 'next'
import { BandejaViaticosView } from '@/features/viaticos/components/bandeja/BandejaViaticosView'

export const metadata: Metadata = {
  title: 'Bandeja de viáticos',
  description: 'Solicitudes, anticipos y liquidaciones que esperan a Financiero',
}

export default function Page() {
  return <BandejaViaticosView />
}
