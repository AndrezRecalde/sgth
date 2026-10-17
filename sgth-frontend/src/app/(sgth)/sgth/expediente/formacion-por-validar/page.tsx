import type { Metadata } from 'next'
import { FormacionPorValidarView } from '@/features/expediente/components/FormacionPorValidarView'

export const metadata: Metadata = {
  title: 'Títulos por validar',
  description: 'Los títulos y cursos que los servidores registran desde el portal, a la espera de Talento Humano',
}

export default function FormacionPorValidarPage() {
  return <FormacionPorValidarView />
}
