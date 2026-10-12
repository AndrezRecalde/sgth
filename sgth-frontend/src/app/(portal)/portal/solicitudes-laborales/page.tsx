import type { Metadata } from 'next'
import { MisSolicitudesView } from '@/features/portal/components/MisSolicitudesView'

export const metadata: Metadata = {
  title: 'Renuncia, licencia y jubilación',
  description: 'Las solicitudes del servidor a Talento Humano: renuncia, licencia sin remuneración y jubilación',
}

export default function MisSolicitudesPage() {
  return <MisSolicitudesView />
}
