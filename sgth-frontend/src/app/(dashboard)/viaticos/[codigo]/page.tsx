import { Metadata } from 'next'
import { ViaticoDetallePage } from '@/features/viaticos/components/ViaticoDetallePage'

export const metadata: Metadata = {
  title: 'Detalle de viático — SGTH GADPE',
}

interface Props {
  params: Promise<{ codigo: string }>
}

export default async function Page({ params }: Props) {
  const { codigo } = await params
  return <ViaticoDetallePage identificador={codigo} />
}
