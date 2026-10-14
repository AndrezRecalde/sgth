import type { Metadata } from 'next'
import { NuevoEquipoView } from './NuevoEquipoView'

export const metadata: Metadata = {
  title: 'Registrar equipo',
  description: 'Registrar un equipo informático en el inventario de TIC',
}

interface Props {
  /** `copiar`: el equipo del que se toman tipo, marca, modelo y características. */
  searchParams: Promise<{ copiar?: string }>
}

export default async function NuevoEquipoPage({ searchParams }: Props) {
  const { copiar } = await searchParams
  const copiarId = Number(copiar)

  return <NuevoEquipoView copiarId={Number.isInteger(copiarId) && copiarId > 0 ? copiarId : null} />
}
