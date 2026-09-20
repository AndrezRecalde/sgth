'use client'

import { useState } from 'react'
import { Stack, Text } from '@mantine/core'
import { IconStethoscope } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, SgthTable } from '@/components/ui'
import { useSolicitudesCertificacion } from '@/features/dispensario/hooks/useSolicitudCertificacion'
import { usePdfFemo } from '@/features/dispensario/hooks/usePdfFemo'
import { getSaludOcupacionalColumns } from '../saludOcupacional.columns'

interface Props { servidorId: number }

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15

export function SaludOcupacionalTab({ servidorId }: Props) {
  const [page, setPage] = useState(1)
  const { data, isLoading, error } = useSolicitudesCertificacion({
    page,
    per_page: POR_PAGINA,
    servidor_id: servidorId,
  })
  const solicitudes = data?.data ?? []

  const { descargarFemo, loading: descargando } = usePdfFemo()
  // El hook tiene un solo `loading`: sin saber qué fila lo pidió, giraban
  // todos los botones de PDF a la vez.
  const [descargandoId, setDescargandoId] = useState<number | null>(null)

  const columns = getSaludOcupacionalColumns({
    descargandoId: descargando ? descargandoId : null,
    onDescargar: (s) => {
      setDescargandoId(s.id)
      descargarFemo(s.ficha_femo_id!, `femo-${s.cedula_paciente}-${s.id}.pdf`)
    },
  })

  return (
    <Stack gap="md">
      <Text size="sm" c="dimmed">
        Historial de certificaciones médicas ocupacionales (FEMO) solicitadas
        para este servidor.
      </Text>

      <DataState
        loading={isLoading}
        error={error}
        empty={solicitudes.length === 0}
        skeletonRows={3}
        emptyProps={{
          icon: IconStethoscope,
          title: 'Sin certificaciones registradas',
          description: 'Este servidor no tiene solicitudes de certificación médica.',
        }}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={solicitudes}
          columns={columns}
          totalRecords={data?.total ?? solicitudes.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={100}
        />
      </DataState>
    </Stack>
  )
}
