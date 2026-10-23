import type { Metadata } from 'next'
import { ExamenesView } from './ExamenesView'
import type { VistaBandeja } from '@/features/dispensario/services/bandejaExamenesService'

export const metadata: Metadata = {
  title: 'Exámenes',
  description: 'Órdenes que esperan resultados, resultados por revisar, perfiles y catálogo de exámenes del Dispensario',
}

interface Props {
  /**
   * `vista`: con qué abre la bandeja. `solicitante`: el médico de «Por
   * revisar»; el enlace de «Mi jornada» trae el propio.
   */
  searchParams: Promise<{ vista?: string; solicitante?: string }>
}

const VISTAS: VistaBandeja[] = ['pendientes', 'vencidas', 'por-revisar']

export default async function ExamenesPage({ searchParams }: Props) {
  const p = await searchParams
  const vista = VISTAS.find((v) => v === p.vista)
  const solicitante = Number(p.solicitante)

  return (
    <ExamenesView
      vista={vista}
      solicitante={Number.isInteger(solicitante) && solicitante > 0 ? solicitante : null}
    />
  )
}
