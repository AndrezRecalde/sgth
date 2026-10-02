import type { Metadata } from 'next'
import { IndicadoresSsoView } from './IndicadoresSsoView'

export const metadata: Metadata = {
  title: 'Indicadores SSO',
  description: 'Índices reactivos del CD 513 e índices proactivos del período',
}

export default function IndicadoresSsoPage() {
  return <IndicadoresSsoView />
}
