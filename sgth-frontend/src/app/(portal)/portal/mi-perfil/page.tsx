import type { Metadata } from 'next'
import { MiPerfilView } from '@/features/portal/components/MiPerfilView'

export const metadata: Metadata = {
  title: 'Mi perfil',
  description: 'La ficha del servidor, su formación académica y su hoja de vida en PDF',
}

export default function MiPerfilPage() {
  return <MiPerfilView />
}
