import type { Metadata } from 'next'
import { CumplimientoView } from './CumplimientoView'

export const metadata: Metadata = {
  title: 'Cumplimiento Normativo',
  description: 'Lista de verificación de la normativa legal de seguridad y salud por período',
}

export default function CumplimientoNormativoPage() {
  return <CumplimientoView />
}
