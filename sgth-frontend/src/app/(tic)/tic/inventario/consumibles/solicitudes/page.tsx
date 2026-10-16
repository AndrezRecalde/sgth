import type { Metadata } from 'next'
import { SolicitudesBodegaView } from './SolicitudesBodegaView'

export const metadata: Metadata = {
  title: 'Solicitudes a bodega',
  description: 'La solicitud de materiales informáticos a bodega: generarla, imprimirla y anotar lo que despachó Bodega',
}

export default function SolicitudesBodegaPage() {
  return <SolicitudesBodegaView />
}
