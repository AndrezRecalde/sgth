'use client'

import { confirmar } from '@/components/ui'
import { useState } from 'react'
import { Group, Button, Text, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import {
  IconPlus, IconChartBar, IconLink, IconLock, IconClipboardList,
} from '@tabler/icons-react'
import { DataState, SgthTable, StatusBadge, TableActions } from '@/components/ui'
import { useCampaniasPsicosocial, usePsicosocialMutations } from '../hooks/usePsicosocial'
import { CrearCampaniaPsicosocialModal } from './CrearCampaniaPsicosocialModal'
import { ResultadosPsicosocialesModal } from './ResultadosPsicosocialesModal'
import type { CampaniaPsicosocial } from '../services/psicosocialService'
import type { DataTableColumn } from 'mantine-datatable'

export function CampaniasPsicosocialTab() {
  const { data: campanias = [], isLoading, error } = useCampaniasPsicosocial()
  const { cerrarCampania } = usePsicosocialMutations()
  const [crearOpened, { open: openCrear, close: closeCrear }] = useDisclosure(false)
  const [resultadosOpened, { open: openResultados, close: closeResultados }] = useDisclosure(false)
  const [campaniaSeleccionada, setCampaniaSeleccionada] = useState<number | null>(null)

  const copiarLink = (codigo: string) => {
    const url = `${window.location.origin}/psicosocial/${codigo}`
    navigator.clipboard.writeText(url).then(() => {
      notifications.show({
        title: 'Enlace copiado',
        message: 'Comparta este enlace con el personal para que responda el cuestionario.',
        color: 'emerald',
      })
    })
  }

  const verResultados = (campania: CampaniaPsicosocial) => {
    setCampaniaSeleccionada(campania.id)
    openResultados()
  }

  const columns: DataTableColumn<CampaniaPsicosocial>[] = [
    { accessor: 'periodo', title: 'Período', width: 100 },
    {
      accessor: 'unidad_administrativa',
      title: 'Unidad',
      render: (c) => c.unidad_administrativa?.nombre ?? 'Toda la institución',
    },
    {
      accessor: 'codigo_acceso',
      title: 'Código',
      width: 120,
      render: (c) => <Text ff="monospace" size="sm">{c.codigo_acceso}</Text>,
    },
    {
      accessor: 'activa',
      title: 'Estado',
      width: 100,
      render: (c) => (
        <StatusBadge tone={c.activa ? 'success' : 'neutral'}>
          {c.activa ? 'Abierta' : 'Cerrada'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'respuestas_count',
      title: 'Respuestas',
      width: 100,
      textAlign: 'center',
      render: (c) => c.respuestas_count ?? 0,
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (campania) => (
        <TableActions
          actions={[
            {
              label: 'Copiar enlace público',
              icon: <IconLink size={14} />,
              onClick: () => copiarLink(campania.codigo_acceso),
            },
            {
              label: 'Ver resultados',
              icon: <IconChartBar size={14} />,
              onClick: () => verResultados(campania),
            },
            {
              label: 'Cerrar campaña',
              icon: <IconLock size={14} />,
              color: 'red',
              hidden: !campania.activa,
              onClick: () => confirmar({
                title:   'Cerrar campaña',
                message: (
                  <>
                    Se cerrará la campaña del período <b>{campania.periodo}</b>.
                    Ya no se podrán registrar más respuestas.
                  </>
                ),
                destructiva: true,
                confirmLabel: 'Cerrar campaña',
                onConfirm: () => cerrarCampania.mutate(campania.id),
              }),
            },
          ]}
        />
      ),
    },
  ]

  return (
    <Stack gap="md">
      <Group justify="space-between" mb="md">
        <Text size="sm" c="dimmed">
          Cuestionario anónimo de evaluación de riesgo psicosocial (Ministerio del Trabajo, 58 ítems).
        </Text>
        <Button leftSection={<IconPlus size={16} />} color="emerald" onClick={openCrear}>
          Nueva campaña
        </Button>
      </Group>

      <DataState
        loading={isLoading}
        error={error}
        empty={!campanias.length}
        emptyProps={{
          icon: IconClipboardList,
          title: 'Sin campañas psicosociales',
          description: 'Aún no se ha abierto ninguna campaña de evaluación.',
        }}
      >
        <SgthTable
          records={campanias}
          columns={columns}
          minHeight={200}
        />
      </DataState>

      <CrearCampaniaPsicosocialModal opened={crearOpened} onClose={closeCrear} />
      <ResultadosPsicosocialesModal
        opened={resultadosOpened}
        onClose={() => { setCampaniaSeleccionada(null); closeResultados() }}
        campaniaId={campaniaSeleccionada}
      />
    </Stack>
  )
}
