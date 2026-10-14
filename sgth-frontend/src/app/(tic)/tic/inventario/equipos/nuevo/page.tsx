import type { Metadata } from 'next'
import { NuevoEquipoView } from './NuevoEquipoView'

export const metadata: Metadata = {
  title: 'Registrar equipo',
  description: 'Registrar un equipo informático en el inventario de TIC',
}

interface Props {
  /**
   * `copiar`: el equipo del que se toman tipo, marca, modelo y características.
   * `constatacion`, `codigo` y `ubicacion`: el alta en sitio desde una
   * constatación física (5.2).
   */
  searchParams: Promise<{ copiar?: string; constatacion?: string; codigo?: string; ubicacion?: string }>
}

const idValido = (v?: string) => {
  const n = Number(v)
  return Number.isInteger(n) && n > 0 ? n : null
}

export default async function NuevoEquipoPage({ searchParams }: Props) {
  const p = await searchParams
  const constatacion = idValido(p.constatacion)
  const ubicacion = idValido(p.ubicacion)

  return (
    <NuevoEquipoView
      copiarId={idValido(p.copiar)}
      enSitio={constatacion && ubicacion ? { constatacionId: constatacion, ubicacionId: ubicacion, codigo: (p.codigo ?? '').slice(0, 50) } : null}
    />
  )
}
