import type { Metadata } from 'next'
import { ConfiguracionTicView } from './ConfiguracionTicView'

export const metadata: Metadata = {
  title: 'Configuración de TIC',
  description: 'Catálogos del inventario, reglas del préstamo, y categorías, SLA y técnicos de la mesa de ayuda',
}

export default function ConfiguracionTicPage() {
  return <ConfiguracionTicView />
}
