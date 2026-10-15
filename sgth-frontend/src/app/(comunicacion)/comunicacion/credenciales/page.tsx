import type { Metadata } from 'next'
import { esSituacionFiltrable } from '@/features/comunicacion/constants/credenciales'
import { CredencialesView } from './CredencialesView'

export const metadata: Metadata = {
  title: 'Credenciales',
  description: 'La credencial de cada servidor: imprimirla, revocarla y recibir la tarjeta',
}

interface Props {
  searchParams: Promise<{ situacion?: string | string[] }>
}

/** `?situacion=` la abre filtrada: es a donde llevan las cifras del Inicio. */
export default async function CredencialesPage({ searchParams }: Props) {
  const { situacion } = await searchParams

  return <CredencialesView filtrosIniciales={esSituacionFiltrable(situacion) ? { situacion } : {}} />
}
