import type { Metadata } from 'next'
import { RequierePermiso } from '@/components/layout/RequierePermiso'
import { ROUTES } from '@/config/routes'
import { NuevoComunicadoView } from './NuevoComunicadoView'

export const metadata: Metadata = {
  title: 'Nueva publicación',
  description: 'Una noticia o un comunicado de Talento Humano para el Portal',
}

export default function NuevoComunicadoPage() {
  return (
    // El listado lo abre también quien solo sigue las lecturas, como el
    // auditor; redactar es de quien tiene el permiso (PR 14).
    <RequierePermiso permiso="redactar-comunicados" volverA={ROUTES.SGTH.COMUNICADOS}>
      <NuevoComunicadoView />
    </RequierePermiso>
  )
}
