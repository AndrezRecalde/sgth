'use client'

import { useState } from 'react'
import { IconCircleX, IconFlask, IconPrinter, IconUpload } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, SgthTable } from '@/components/ui'
import { useAuthStore } from '@/store/auth.store'
import { useOrdenExamenPdf } from '../../hooks/useExamenes'
import { SubirResultadoModal } from '../SubirResultadoModal'
import { NoRealizadoModal } from './NoRealizadoModal'
import { columnasBandejaExamenes } from './bandejaExamenes.columns'
import type { PaginatedResponse } from '@/types/api'
import type { OrdenBandeja } from '../../services/bandejaExamenesService'

interface Props {
  vencidas:     boolean
  buscar:       string
  pagina?:      PaginatedResponse<OrdenBandeja>
  cargando:     boolean
  error:        unknown
  onReintentar: () => void
  page:         number
  porPagina:    number
  onPage:       (page: number) => void
}

/**
 * Las órdenes que esperan resultados. El médico registra desde aquí lo que trae
 * el paciente sin abrir una consulta; Enfermería la mira para llamar a quien no
 * volvió.
 */
export function OrdenesBandeja({ vencidas, buscar, pagina, cargando, error, onReintentar, page, porPagina, onPage }: Props) {
  const { hasRole } = useAuthStore()
  const [registrando, setRegistrando] = useState<OrdenBandeja | null>(null)
  const [sinRealizar, setSinRealizar] = useState<OrdenBandeja | null>(null)
  const { abrir: abrirPdf } = useOrdenExamenPdf()
  const ordenes = pagina?.data ?? []

  // Lo que vuelve lo registran el médico y el odontólogo (D2, P1).
  const puedeRegistrar = hasRole('medico') || hasRole('odontologo')

  return (
    <>
      <DataState loading={cargando} error={error} onRetry={onReintentar} page={page}
        errorTitle="No se pudo cargar la bandeja" empty={!ordenes.length}
        emptyProps={{
          icon: IconFlask,
          title: vencidas ? 'Sin órdenes vencidas' : 'Sin órdenes pendientes',
          description: buscar
            ? 'Ninguna orden coincide con la búsqueda.'
            : vencidas
              ? 'Ninguna orden pasó los 90 días sin sus resultados.'
              : 'No hay órdenes esperando resultados.',
        }}>
        <SgthTable
          {...PAGINACION_ES}
          records={ordenes}
          minHeight={200}
          totalRecords={pagina?.total ?? 0}
          recordsPerPage={porPagina}
          page={page}
          onPageChange={onPage}
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

      <SubirResultadoModal opened={registrando !== null} onClose={() => setRegistrando(null)}
        historiaClinicaId={registrando?.historia_clinica_id ?? 0} ordenInicial={registrando} />
      <NoRealizadoModal orden={sinRealizar} onClose={() => setSinRealizar(null)} />
    </>
  )
}
