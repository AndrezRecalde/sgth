import type { Metadata } from 'next'
import { CargasPorValidarView } from '@/features/expediente/components/CargasPorValidarView'

export const metadata: Metadata = {
  title: 'Cargas por validar',
  description: 'Las cargas familiares que los servidores piden desde el portal, a la espera de Talento Humano',
}

interface Props {
  /** `?servidor=` deja solo las de un servidor: es el enlace del expediente. */
  searchParams: Promise<{ servidor?: string }>
}

export default async function CargasPorValidarPage({ searchParams }: Props) {
  const { servidor } = await searchParams
  const servidorId = Number(servidor)

  return <CargasPorValidarView servidorId={Number.isInteger(servidorId) && servidorId > 0 ? servidorId : null} />
}
