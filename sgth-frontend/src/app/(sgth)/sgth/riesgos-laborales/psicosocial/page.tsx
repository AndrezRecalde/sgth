import type { Metadata } from 'next'
import { CampaniasPsicosocialView } from './CampaniasPsicosocialView'

export const metadata: Metadata = {
  title: 'Evaluación Psicosocial',
  description: 'Campañas del cuestionario anónimo del Ministerio del Trabajo y sus resultados',
}

export default function EvaluacionPsicosocialPage() {
  return <CampaniasPsicosocialView />
}
