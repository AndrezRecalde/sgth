import type { Metadata } from 'next'
import { DisponibilidadView } from './DisponibilidadView'

export const metadata: Metadata = {
  title: 'Préstamo de equipos',
  description: 'Disponibilidad de los equipos que presta la Unidad de TIC y su solicitud',
}

export default function PrestamoEquiposPage() {
  return <DisponibilidadView />
}
