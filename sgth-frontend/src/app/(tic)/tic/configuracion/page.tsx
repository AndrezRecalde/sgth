import type { Metadata } from 'next'
import { ConfiguracionTicView } from './ConfiguracionTicView'

export const metadata: Metadata = {
  title: 'Configuración de TIC',
  description: 'Catálogos del inventario: tipos, marcas, ubicaciones y contratos de vigencia',
}

export default function ConfiguracionTicPage() {
  return <ConfiguracionTicView />
}
