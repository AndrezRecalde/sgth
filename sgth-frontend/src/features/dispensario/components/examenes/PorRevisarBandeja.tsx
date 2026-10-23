'use client'

import { useState } from 'react'
import { Select, Stack } from '@mantine/core'
import { IconClipboardCheck, IconExternalLink, IconFileText } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, SgthTable } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAuthStore } from '@/store/auth.store'
import { usePersonalMedico } from '../../hooks/useAgenda'
import { useResultadosPorRevisar } from '../../hooks/useBandejaExamenes'
import { useVerResultado } from '../../hooks/useResultadoMedico'
import { RevisarResultadoModal } from './RevisarResultadoModal'
import { columnasPorRevisar } from './porRevisar.columns'
import type { ResultadoPorRevisar } from '../../services/bandejaExamenesService'

const POR_PAGINA = 15

interface Props {
  buscar:              string
  /** El médico con el que abre: el enlace de «Mi jornada» trae el propio. */
  solicitanteInicial?: number | null
}

/**
 * Los resultados que nadie ha leído (D3), filtrables por el médico que responde
 * por ellos. Cualquier médico puede revisarlos si quien los pidió no está.
 */
export function PorRevisarBandeja({ buscar, solicitanteInicial = null }: Props) {
  const contained = useContainedInput()
  const { hasRole } = useAuthStore()
  const [solicitante, setSolicitante] = useState<string | null>(solicitanteInicial ? String(solicitanteInicial) : null)
  const [page, setPage] = useState(1)
  const [revisando, setRevisando] = useState<ResultadoPorRevisar | null>(null)
  const ver = useVerResultado()

  const { data: personal = [] } = usePersonalMedico()
  const medicos = personal
    .filter((p) => p.roles.includes('medico') || p.roles.includes('odontologo'))
    .sort((a, b) => a.nombre_completo.localeCompare(b.nombre_completo, 'es'))

  const { data, isLoading, error, refetch } = useResultadosPorRevisar({
    solicitante: solicitante ? Number(solicitante) : undefined, buscar, page, per_page: POR_PAGINA,
  })
  const resultados = data?.data ?? []
  const puedeRevisar = hasRole('medico') || hasRole('odontologo')

  return (
    <Stack gap="sm">
      <Select label="Responde" placeholder="Todos los médicos" clearable searchable {...contained} w={280}
        description="Quien pidió la orden o, sin orden, quien atendió la consulta"
        data={medicos.map((m) => ({ value: String(m.id), label: m.nombre_completo }))}
        value={solicitante} onChange={(v) => { setSolicitante(v); setPage(1) }} />

      <DataState loading={isLoading} error={error} onRetry={() => refetch()} page={page}
        errorTitle="No se pudieron cargar los resultados por revisar" empty={!resultados.length}
        emptyProps={{
          icon: IconFileText,
          title: 'Nada por revisar',
          description: buscar || solicitante
            ? 'Ningún resultado por revisar coincide con el filtro.'
            : 'Todos los resultados subidos ya tienen su lectura.',
        }}>
        <SgthTable
          {...PAGINACION_ES}
          records={resultados}
          minHeight={200}
          totalRecords={data?.total ?? 0}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          columns={columnasPorRevisar((r) => [
            { label: 'Revisar', icon: <IconClipboardCheck size={14} />, hidden: !puedeRevisar, onClick: () => setRevisando(r) },
            { label: 'Ver archivo', icon: <IconExternalLink size={14} />, onClick: () => ver.mutate(r) },
          ])}
        />
      </DataState>

      <RevisarResultadoModal resultado={revisando} onClose={() => setRevisando(null)} />
    </Stack>
  )
}
