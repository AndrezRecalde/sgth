'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Group, Stack, Switch } from '@mantine/core'
import { IconInbox } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, SgthTable } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useBandejaEtapa } from '../../hooks/useBandejaViaticos'
import { getBandejaColumns } from './bandeja.columns'
import type { EtapaBandeja, FiltrosBandeja } from '@/types/api'

const VACIO: Record<EtapaBandeja, string> = {
  por_aprobar:  'No hay solicitudes esperando aprobación.',
  por_anticipo: 'No hay anticipos por entregar.',
  por_iniciar:  'No hay viáticos listos para salir.',
  en_comision:  'Nadie está en comisión ahora.',
  por_liquidar: 'No hay liquidaciones pendientes.',
  por_revisar:  'No hay liquidaciones por revisar.',
  cerrados:     'Todavía no hay viáticos cerrados.',
}

interface Props {
  etapa: EtapaBandeja
  filtros: FiltrosBandeja
}

/** Los viáticos de una pestaña. En «por liquidar» se puede ver solo lo vencido. */
export function BandejaEtapaTabla({ etapa, filtros }: Props) {
  const router = useRouter()
  const [page, setPage] = useState(1)
  const [vencidas, setVencidas] = useState(false)

  const { data, isLoading, error } = useBandejaEtapa(etapa, filtros, page, vencidas)
  const filas = data?.data ?? []

  return (
    <Stack gap="sm">
      {etapa === 'por_liquidar' && (
        <Group justify="flex-end">
          <Switch
            label="Solo vencidas"
            checked={vencidas}
            onChange={(e) => {
              setVencidas(e.currentTarget.checked)
              setPage(1)
            }}
          />
        </Group>
      )}

      <DataState
        loading={isLoading}
        error={error}
        empty={filas.length === 0}
        emptyProps={{ icon: IconInbox, title: 'Nada en esta etapa', description: VACIO[etapa] }}
      >
        <SgthTable
          records={filas}
          columns={getBandejaColumns(etapa, (v) => router.push(ROUTES.PORTAL.VIATICO_DETALLE(v.codigo_viatico ?? v.id)))}
          onRowClick={({ record }) => router.push(ROUTES.PORTAL.VIATICO_DETALLE(record.codigo_viatico ?? record.id))}
          totalRecords={data?.total ?? 0}
          recordsPerPage={15}
          page={page}
          onPageChange={setPage}
          {...PAGINACION_ES}
          minHeight={200}
          pinLastColumn
        />
      </DataState>
    </Stack>
  )
}
