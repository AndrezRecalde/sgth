import type { Metadata } from 'next'
import { ConsumiblesView } from './ConsumiblesView'

export const metadata: Metadata = {
  title: 'Consumibles',
  description: 'Tóner, tinta y repuestos: el saldo de lo que guarda TIC y las entregas a cada equipo',
}

export default function ConsumiblesPage() {
  return <ConsumiblesView />
}
