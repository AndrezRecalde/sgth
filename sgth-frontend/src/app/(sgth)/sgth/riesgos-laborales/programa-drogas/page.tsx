import type { Metadata } from 'next'
import { ProgramaDrogasView } from './ProgramaDrogasView'

export const metadata: Metadata = {
  title: 'Programa de Prevención de Drogas',
  description: 'Matriz de seguimiento de las seis fases del programa (MDT-MSP-2019-038)',
}

export default function ProgramaDrogasPage() {
  return <ProgramaDrogasView />
}
