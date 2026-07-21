'use client'

import { useState } from 'react'
import { Box, Group, Button, Badge, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import {
  IconPlus, IconChartBar, IconLink, IconLock,
} from '@tabler/icons-react'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { useCampaniasPsicosocial, usePsicosocialMutations } from '../hooks/usePsicosocial'
import { CrearCampaniaPsicosocialModal } from './CrearCampaniaPsicosocialModal'
import { ResultadosPsicosocialesModal } from './ResultadosPsicosocialesModal'
import type { CampaniaPsicosocial } from '../services/psicosocialService'
import type { DataTableColumn } from 'mantine-datatable'

export function CampaniasPsicosocialTab() {
  const { data: campanias = [], isLoading } = useCampaniasPsicosocial()
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
        <Badge color={c.activa ? 'emerald' : 'gray'} variant="light" size="sm">
          {c.activa ? 'Abierta' : 'Cerrada'}
        </Badge>
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
              onClick: () => {
                if (confirm(`¿Cerrar la campaña del período ${campania.periodo}? Ya no se podrán registrar más respuestas.`)) {
                  cerrarCampania.mutate(campania.id)
                }
              },
            },
          ]}
        />
      ),
    },
  ]

  return (
    <Box>
      <Group justify="space-between" mb="md">
        <Text size="sm" c="dimmed">
          Cuestionario anónimo de evaluación de riesgo psicosocial (Ministerio del Trabajo, 58 ítems).
        </Text>
        <Button leftSection={<IconPlus size={16} />} color="emerald" onClick={openCrear}>
          Nueva campaña
        </Button>
      </Group>

      <SgthTable
        records={campanias}
        columns={columns}
        fetching={isLoading}
        minHeight={200}
      />

      <CrearCampaniaPsicosocialModal opened={crearOpened} onClose={closeCrear} />
      <ResultadosPsicosocialesModal
        opened={resultadosOpened}
        onClose={() => { setCampaniaSeleccionada(null); closeResultados() }}
        campaniaId={campaniaSeleccionada}
      />
    </Box>
  )
}
