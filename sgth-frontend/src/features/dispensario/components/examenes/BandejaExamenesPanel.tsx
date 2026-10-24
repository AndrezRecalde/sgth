'use client'

import { useState } from 'react'
import { Group, SegmentedControl, Stack, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { SectionCard } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useBandejaExamenes } from '../../hooks/useBandejaExamenes'
import { OrdenesBandeja } from './OrdenesBandeja'
import { PorRevisarBandeja } from './PorRevisarBandeja'
import { PorValidarBandeja } from './PorValidarBandeja'
import type { VistaBandeja } from '../../services/bandejaExamenesService'

const POR_PAGINA = 15

const VISTAS: VistaBandeja[] = ['pendientes', 'vencidas', 'por-revisar', 'por-validar']
const esVista = (v: string): v is VistaBandeja => VISTAS.some((x) => x === v)

interface Props {
  vistaInicial?:       VistaBandeja
  /** Para «Por revisar»: el médico con el que abre (el enlace de «Mi jornada»). */
  solicitanteInicial?: number | null
}

/**
 * La bandeja de exámenes (5.4 del diseño): las órdenes que esperan resultados,
 * las vencidas aparte para que alguien las vea, los resultados que nadie ha
 * leído todavía (D3) y lo que los pacientes enviaron desde el Portal (fase 6).
 */
export function BandejaExamenesPanel({ vistaInicial = 'pendientes', solicitanteInicial = null }: Props) {
  const contained = useContainedInput()
  const [vista, setVista] = useState<VistaBandeja>(vistaInicial)
  const [buscar, setBuscar] = useState('')
  const [buscado] = useDebouncedValue(buscar.trim(), 350)
  const [page, setPage] = useState(1)

  // Trae también los recuentos de las vistas, por eso se pide siempre.
  const ordenes = useBandejaExamenes({
    vista: vista === 'vencidas' ? 'vencidas' : 'pendientes', buscar: buscado, page, per_page: POR_PAGINA,
  })
  const resumen = ordenes.data?.resumen
  const cuenta = (n?: number) => (n === undefined ? '' : ` (${n})`)

  return (
    <SectionCard title="Bandeja de exámenes"
      description="Lo más antiguo primero. Busque por folio, cédula o nombre del paciente.">
      <Stack gap="sm">
        <Group gap="sm" align="flex-end" justify="space-between">
          <SegmentedControl size="xs" value={vista}
            onChange={(v) => { setVista(esVista(v) ? v : 'pendientes'); setPage(1) }}
            data={[
              { value: 'pendientes',  label: `Pendientes${cuenta(resumen?.pendientes)}` },
              { value: 'vencidas',    label: `Vencidas${cuenta(resumen?.vencidas)}` },
              { value: 'por-revisar', label: `Por revisar${cuenta(resumen?.por_revisar)}` },
              { value: 'por-validar', label: `Del Portal${cuenta(resumen?.por_validar)}` },
            ]} />
          <TextInput label="Buscar" placeholder="Folio, cédula o nombre…"
            {...contained} w={280} value={buscar}
            onChange={(e) => { setBuscar(e.currentTarget.value); setPage(1) }} />
        </Group>

        {vista === 'por-revisar' ? (
          <PorRevisarBandeja buscar={buscado} solicitanteInicial={solicitanteInicial} />
        ) : vista === 'por-validar' ? (
          <PorValidarBandeja buscar={buscado} />
        ) : (
          <OrdenesBandeja vencidas={vista === 'vencidas'} buscar={buscado} pagina={ordenes.data?.pagina}
            cargando={ordenes.isLoading} error={ordenes.error} onReintentar={() => ordenes.refetch()}
            page={page} porPagina={POR_PAGINA} onPage={setPage} />
        )}
      </Stack>
    </SectionCard>
  )
}
