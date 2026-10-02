import type { Metadata } from 'next'
import { DashboardSsoView } from './DashboardSsoView'

export const metadata: Metadata = {
  title: 'Riesgos Laborales (SSO)',
  description: 'Resumen del período: riesgos, accidentes, índices del CD 513, cumplimiento y tamizajes',
}

export default function RiesgosLaboralesIndexPage() {
  return <DashboardSsoView />
}
