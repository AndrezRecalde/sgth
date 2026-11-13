import type { Metadata } from 'next'
import { BrechasDePerfilView } from '@/features/expediente/components/BrechasDePerfilView'

export const metadata: Metadata = {
  title: 'Brechas de perfil',
  description: 'Cada servidor frente al perfil de su puesto: instrucción y capacitación, para el plan de capacitación',
}

export default function BrechasDePerfilPage() {
  return <BrechasDePerfilView />
}
