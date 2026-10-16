import type { Metadata } from 'next'
import { EntregarConsumiblesView } from './EntregarConsumiblesView'

export const metadata: Metadata = {
  title: 'Entregar consumibles',
  description: 'Entregar tóner, tinta o repuestos a un equipo, con el lector QR desde el celular',
}

interface Props {
  searchParams: Promise<{ bien?: string }>
}

export default async function EntregarConsumiblesPage({ searchParams }: Props) {
  const { bien } = await searchParams

  return <EntregarConsumiblesView bienId={bien ? Number(bien) : null} />
}
