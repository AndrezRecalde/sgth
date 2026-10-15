import type { Metadata } from 'next'
import { CredencialesView } from './CredencialesView'

export const metadata: Metadata = {
  title: 'Credenciales',
  description: 'La credencial de cada servidor: imprimirla, revocarla y recibir la tarjeta',
}

export default function CredencialesPage() {
  return <CredencialesView />
}
