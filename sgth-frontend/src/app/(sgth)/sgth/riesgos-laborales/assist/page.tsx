import type { Metadata } from 'next'
import { AssistCampaniasView } from './AssistCampaniasView'

export const metadata: Metadata = {
  title: 'Tamizaje ASSIST',
  description: 'Campañas de tamizaje anónimo de consumo de sustancias (OMS/OPS) y sus resultados',
}

export default function TamizajeAssistPage() {
  return <AssistCampaniasView />
}
