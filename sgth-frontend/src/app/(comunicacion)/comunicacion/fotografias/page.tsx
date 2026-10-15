import type { Metadata } from 'next'
import { FotografiasView } from './FotografiasView'

export const metadata: Metadata = {
  title: 'Fotografías',
  description: 'Las fotografías de los servidores, para su credencial',
}

interface Props {
  searchParams: Promise<{ foto?: string | string[]; unidad?: string | string[] }>
}

/** `?foto=sin&unidad=` la abre filtrada: es a donde lleva el Inicio. */
export default async function FotografiasPage({ searchParams }: Props) {
  const { foto, unidad } = await searchParams
  const unidadId = typeof unidad === 'string' && /^\d+$/.test(unidad) ? Number(unidad) : undefined

  return (
    <FotografiasView
      filtrosIniciales={{
        ...(foto === 'sin' || foto === 'con' ? { foto } : {}),
        ...(unidadId ? { unidad_administrativa_id: unidadId } : {}),
      }}
    />
  )
}
