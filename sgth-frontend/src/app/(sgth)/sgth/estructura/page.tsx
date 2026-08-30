import type { Metadata } from 'next'
import { OrganigramaView } from './OrganigramaView'

export const metadata: Metadata = {
  title: 'Organigrama',
  description: 'Estructura orgánica del GAD Provincial de Esmeraldas',
}

export default function OrganigramaPage() {
  return <OrganigramaView />
}
