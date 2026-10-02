import type { Metadata } from 'next'
import { RiesgosLaboralesView } from './RiesgosLaboralesView'

export const metadata: Metadata = {
  title: 'Factores de Riesgo Laboral',
  description: 'Matriz de riesgos por puesto, valorada con la NTP 330',
}

export default function FactoresRiesgoPage() {
  return <RiesgosLaboralesView />
}
