import type { Metadata } from 'next'
import { ConsolidadoPermisosView } from './ConsolidadoPermisosView'

export const metadata: Metadata = {
  title: 'Consolidado de permisos',
  description: 'Reporte consolidado de permisos del GAD Provincial de Esmeraldas',
}

export default function ConsolidadoPermisosPage() {
  return <ConsolidadoPermisosView />
}
