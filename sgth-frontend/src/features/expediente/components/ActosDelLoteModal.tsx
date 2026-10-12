'use client'

import { Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { DataState, SgthModal, SgthTable } from '@/components/ui'
import { useLoteAccion } from '../hooks/useContratoColectivo'
import type { ActoDelLote } from '../services/contratoColectivoService'
import { ESTADO_LABELS } from '../utils/estadoAccionPersonal'
import { dinero } from '../utils/dinero'

/**
 * Los actos de un lote, con su estado y su número. Lo abren la pestaña «Actos
 * en lote» y, desde la fase 7.2, la ficha de cualquier acto que nació en uno.
 */
export function ActosDelLoteModal({ loteId, onClose }: { loteId: number | null; onClose: () => void }) {
  const { data, isLoading, error, refetch } = useLoteAccion(loteId)
  const traspaso = data?.tipo === 'traspaso_puesto'

  const columnas: DataTableColumn<ActoDelLote>[] = [
    {
      accessor: 'nombre',
      title: data?.tipo === 'incremento_obreros' ? 'Obrero' : 'Ocupante',
      render: (a) => (
        <div>
          <Text size="sm">{a.nombre}</Text>
          <Text size="xs" c="dimmed">{a.cedula ?? '—'}</Text>
        </div>
      ),
    },
    // En el traspaso la remuneración no cambia: el puesto se va con ella.
    ...(traspaso ? [] : [
      { accessor: 'remuneracion_actual', title: 'Actual', width: 110, render: (a: ActoDelLote) => dinero(a.remuneracion_actual) },
      { accessor: 'remuneracion_nueva', title: 'Nueva', width: 110, render: (a: ActoDelLote) => dinero(a.remuneracion_nueva) },
    ]),
    {
      accessor: 'estado',
      title: 'Estado',
      width: 170,
      render: (a) => (
        <div>
          <Text size="sm">{ESTADO_LABELS[a.estado]}</Text>
          {a.codigo_registro && <Text size="xs" c="dimmed">{a.codigo_registro}</Text>}
        </div>
      ),
    },
  ]

  return (
    <SgthModal opened={loteId != null} onClose={onClose} title="Actos del lote" size="xl">
      <DataState loading={isLoading} error={error} errorTitle="No se pudo cargar el lote" onRetry={refetch}>
        <SgthTable records={data?.actos ?? []} columns={columnas} idAccessor="movimiento_id" minHeight={120} maxHeight={480} />
      </DataState>
    </SgthModal>
  )
}
