import type { Metadata } from 'next'
import { DisciplinarioView } from './DisciplinarioView'

export const metadata: Metadata = {
  title: 'Régimen Disciplinario',
  description: 'Sumarios administrativos y vistos buenos del GAD Provincial de Esmeraldas',
}

export default function DisciplinarioPage() {
  return <DisciplinarioView />
}
