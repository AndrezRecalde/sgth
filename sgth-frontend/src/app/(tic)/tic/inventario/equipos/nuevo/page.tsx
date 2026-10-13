import type { Metadata } from 'next'
import { NuevoEquipoView } from './NuevoEquipoView'

export const metadata: Metadata = {
  title: 'Registrar equipo',
  description: 'Registrar un equipo informático en el inventario de TIC',
}

export default function NuevoEquipoPage() {
  return <NuevoEquipoView />
}
