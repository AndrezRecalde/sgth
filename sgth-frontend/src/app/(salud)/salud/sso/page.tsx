import type { Metadata } from 'next'
import { SsoView } from './SsoView'

export const metadata: Metadata = {
  title: 'Solicitudes de evaluación médica',
  description: 'Bandeja de evaluaciones médicas ocupacionales que pide Talento Humano',
}

export default function SsoPage() {
  return <SsoView />
}
