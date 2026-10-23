'use client'

import { useState } from 'react'
import { Group, SegmentedControl, Stack, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconCircleX, IconFlask, IconPrinter, IconUpload } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, SectionCard, SgthTable } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAuthStore } from '@/store/auth.store'
import { useBandejaExamenes } from '../../hooks/useBandejaExamenes'
import { useOrdenExamenPdf } from '../../hooks/useExamenes'
import { SubirResultadoModal } from '../SubirResultadoModal'
import { NoRealizadoModal } from './NoRealizadoModal'
import { columnasBandejaExamenes } from './bandejaExamenes.columns'
import type { OrdenBandeja, VistaBandeja } from '../../services/bandejaExamenesService'

const POR_PAGINA = 15

/**
 * Las órdenes que esperan resultados (5.4 del diseño). El médico registra desde
 * aquí lo que trae el paciente sin abrir una consulta; Enfermería la mira para
 * llamar a quien no volvió. Las vencidas, aparte, para que alguien las vea.
 */
export function BandejaExamenesPanel() {
  const contained = useContainedInput()
  const { hasRole } = useAuthStore()
  const [vista, setVista] = useState<VistaBandeja>('pendientes')
  const [buscar, setBuscar] = useState('')
  const [buscado] = useDebouncedValue(buscar.trim(), 350)
  const [page, setPage] = useState(1)
  const [registrando, setRegistrando] = useState<OrdenBandeja | null>(null)
  const [sinRealizar, setSinRealizar] = useState<OrdenBandeja | null>(null)
  const { abrir: abrirPdf } = useOrdenExamenPdf()

  const { data, isLoading, error, refetch } = useBandejaExamenes({ vista, buscar: buscado, page, per_page: POR_PAGINA })
  const ordenes = data?.pagina.data ?? []
  const resumen = data?.resumen

  // Lo que vuelve lo registran el médico y el odontólogo (D2, P1).
  const puedeRegistrar = hasRole('medico') || hasRole('odontologo')

  return (
    <SectionCard title="Órdenes que esperan resultados"
      description="Las más antiguas primero. Busque por folio, cédula o nombre del paciente.">
      <Stack gap="sm">
        <Group gap="sm" align="flex-end" justify="space-between">
          <SegmentedControl size="xs" value={vista}
            onChange={(v) => { setVista(v === 'vencidas' ? 'vencidas' : 'pendientes'); setPage(1) }}
            data={[
              { value: 'pendientes', label: `Pendientes${resumen ? ` (${resumen.pendientes})` : ''}` },
              { value: 'vencidas',   label: `Vencidas${resumen ? ` (${resumen.vencidas})` : ''}` },
            ]} />
          <TextInput label="Buscar" placeholder="Folio, cédula o nombre…"
            {...contained} w={280} value={buscar}
            onChange={(e) => { setBuscar(e.currentTarget.value); setPage(1) }} />
        </Group>

        <DataState loading={isLoading} error={error} onRetry={() => refetch()} page={page}
          errorTitle="No se pudo cargar la bandeja" empty={!ordenes.length}
          emptyProps={{
            icon: IconFlask,
            title: vista === 'vencidas' ? 'Sin órdenes vencidas' : 'Sin órdenes pendientes',
            description: buscado
              ? 'Ninguna orden coincide con la búsqueda.'
              : vista === 'vencidas'
                ? 'Ninguna orden pasó los 90 días sin sus resultados.'
                : 'No hay órdenes esperando resultados.',
          }}>
          <SgthTable
            {...PAGINACION_ES}
            records={ordenes}
            minHeight={200}
            totalRecords={data?.pagina.total ?? 0}
            recordsPerPage={POR_PAGINA}
            page={page}
            onPageChange={setPage}
            columns={columnasBandejaExamenes((o) => [
              { label: 'Registrar resultado', icon: <IconUpload size={14} />, hidden: !puedeRegistrar, onClick: () => setRegistrando(o) },
              {
                label: 'Marcar no realizado', icon: <IconCircleX size={14} />,
                hidden: !puedeRegistrar || !o.items.some((i) => i.estado === 'pendiente'),
                onClick: () => setSinRealizar(o),
              },
              { label: 'Imprimir orden', icon: <IconPrinter size={14} />, onClick: () => abrirPdf(o.id, o.folio) },
            ])}
          />
        </DataState>
      </Stack>

      <SubirResultadoModal opened={registrando !== null} onClose={() => setRegistrando(null)}
        historiaClinicaId={registrando?.historia_clinica_id ?? 0} ordenInicial={registrando} />
      <NoRealizadoModal orden={sinRealizar} onClose={() => setSinRealizar(null)} />
    </SectionCard>
  )
}
