import type { Metadata } from 'next'
import { OrganigramaPublicoView } from './OrganigramaPublicoView'

export const metadata: Metadata = {
  title: 'Organigrama institucional',
  description:
    'Estructura orgánica del GAD Provincial de Esmeraldas: unidades administrativas y sus subprocesos.',
  // El resto del sistema va con `noindex` porque es una aplicación privada.
  // Esta pantalla es lo contrario: información pública de la institución, y
  // se publica para que se encuentre.
  robots: { index: true, follow: true },
}

export default function OrganigramaPublicoPage() {
  return <OrganigramaPublicoView />
}
