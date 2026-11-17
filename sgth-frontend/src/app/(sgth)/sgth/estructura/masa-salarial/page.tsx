import type { Metadata } from 'next'
import { MasaSalarialView } from './MasaSalarialView'

export const metadata: Metadata = {
  title: 'Masa salarial',
  description: 'Suma referencial de las remuneraciones registradas en el GAD Provincial de Esmeraldas',
}

export default function MasaSalarialPage() {
  return <MasaSalarialView />
}
