'use client'

import { useState } from 'react'
import { Stack, Text } from '@mantine/core'
import { IconArrowBackUp, IconScale } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { DataState, MotivoModal, SectionHeading, SgthTable, StatusBadge, TableActions } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { formatFecha } from '@/lib/fecha'
import { dinero } from '@/features/expediente/utils/dinero'
import { useEscalasRemuneracion, useRevertirEscala } from '../hooks/useEscalaRemuneracion'
import type { EscalaRemuneracion } from '../services/escalaRemuneracionService'

/**
 * Las escalas de remuneraciones registradas (fase 7.3b), con lo que subió cada
 * una. La que se registró por error se revierte entera, mientras nada haya
 * cambiado después.
 */
export function EscalasRemuneracionSection() {
  const { hasPermiso } = useAuth()
  const puedeRevertir = hasPermiso('anular-accion-personal')
  const { data: escalas = [], isLoading, error, refetch } = useEscalasRemuneracion()
  const revertir = useRevertirEscala()
  const [aRevertir, setARevertir] = useState<EscalaRemuneracion | null>(null)

  const columnas: DataTableColumn<EscalaRemuneracion>[] = [
    {
      accessor: 'instrumento',
      title: 'Resolución',
      render: (e) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>{e.instrumento}</Text>
          <Text size="xs" c="dimmed">del {formatFecha(e.fecha_instrumento)} · dictamen {e.dictamen_presupuestario_ref}</Text>
        </Stack>
      ),
    },
    { accessor: 'rige_desde', title: 'Rige desde', render: (e) => formatFecha(e.rige_desde) },
    {
      accessor: 'grados',
      title: 'Grados',
      render: (e) => (
        <Stack gap={0}>
          {e.grados.map((g) => (
            <Text key={g.grado ?? ''} size="xs">{g.grado} · {dinero(g.rmu_anterior)} → <b>{dinero(g.rmu_nueva)}</b></Text>
          ))}
        </Stack>
      ),
    },
    { accessor: 'vinculos', title: 'Servidores que subieron', textAlign: 'right' },
    {
      accessor: 'revertida_en',
      title: 'Estado',
      render: (e) => e.revertida_en
        ? <StatusBadge tone="neutral">Revertida</StatusBadge>
        : <StatusBadge tone="success">Aplicada</StatusBadge>,
    },
    {
      accessor: 'acciones',
      title: '',
      render: (e) => (
        <TableActions
          actions={puedeRevertir && !e.revertida_en
            ? [{ label: 'Revertir la escala', icon: <IconArrowBackUp size={14} />, color: 'red', onClick: () => setARevertir(e) }]
            : []}
        />
      ),
    },
  ]

  return (
    <Stack gap="sm">
      <SectionHeading title="Escalas registradas" mb={0} />
      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las escalas"
        onRetry={refetch}
        empty={!escalas.length}
        emptyProps={{
          icon: IconScale,
          title: 'Sin escalas registradas',
          description: 'Aquí aparecen las resoluciones que suben la RMU de los grados, con lo que subió cada una.',
        }}
      >
        <SgthTable records={escalas} columns={columnas} minHeight={120} />
      </DataState>

      <MotivoModal
        opened={!!aRevertir}
        onClose={() => setARevertir(null)}
        title="Revertir la escala"
        descripcion={aRevertir
          ? `La RMU de los grados y la remuneración de los ${aRevertir.vinculos} servidores vuelven a la de antes, con su constancia en la bitácora. Solo si nada cambió después.`
          : ''}
        confirmLabel="Revertir"
        destructiva
        cargando={revertir.isPending}
        onConfirm={(motivo) => aRevertir && revertir.mutate(
          { id: aRevertir.id, motivo },
          { onSuccess: () => setARevertir(null) },
        )}
      />
    </Stack>
  )
}
