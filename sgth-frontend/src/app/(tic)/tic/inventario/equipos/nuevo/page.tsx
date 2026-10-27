import type { Metadata } from 'next'
import { RequierePermiso } from '@/components/layout/RequierePermiso'
import { ROUTES } from '@/config/routes'
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
    // El listado lo abre también quien solo consulta el inventario.
    <RequierePermiso permiso="gestionar-inventario-ti" volverA={ROUTES.TIC.EQUIPOS}>
      <NuevoEquipoView
        copiarId={idValido(p.copiar)}
        enSitio={constatacion && ubicacion ? { constatacionId: constatacion, ubicacionId: ubicacion, codigo: (p.codigo ?? '').slice(0, 50) } : null}
      />
    </RequierePermiso>
  )
}
