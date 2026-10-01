'use client'

import { confirmar, notificar } from '@/components/ui'
import { useState } from 'react'
import { Group, Button, Text, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import {
  IconPlus, IconChartBar, IconLink, IconLock, IconClipboardList,
} from '@tabler/icons-react'
import { DataState, SgthTable, StatusBadge, TableActions } from '@/components/ui'
import { useCampaniasAssist, useAssistMutations } from '../hooks/useAssist'
import { CrearCampaniaAssistModal } from './CrearCampaniaAssistModal'
import { ResultadosAssistModal } from './ResultadosAssistModal'
import type { CampaniaAssist } from '../services/assistService'
import {
  ESTADO_CAMPANIA_LABELS, TONO_ESTADO_CAMPANIA,
} from '../constants/campania'
import { formatFecha } from '@/lib/fecha'
import type { DataTableColumn } from 'mantine-datatable'

export function AssistCampaniasTab() {
  const { data: campanias = [], isLoading, error } = useCampaniasAssist()
  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  // Ofrecerlas a quien solo lee serviría para que recibiera un 403.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const { cerrarCampania } = useAssistMutations()
  const [crearOpened, { open: openCrear, close: closeCrear }] = useDisclosure(false)
  const [resultadosOpened, { open: openResultados, close: closeResultados }] = useDisclosure(false)
  const [campaniaSeleccionada, setCampaniaSeleccionada] = useState<number | null>(null)

  const copiarLink = (codigo: string) => {
    const url = `${window.location.origin}${ROUTES.PUBLICO.ASSIST(codigo)}`
    navigator.clipboard.writeText(url).then(() => {
      notificar.exito(
        'Enlace copiado',
        'Comparta este enlace con el personal para que responda el tamizaje.',
      )
    }).catch(() => {
      // El portapapeles no está disponible en contexto no seguro y el permiso
      // se puede denegar: sin esto la promesa se rechazaba en silencio y quien
      // pulsaba no sabía si el enlace se había copiado.
      // Sin cierre automático: el enlace hay que poder leerlo para copiarlo.
      notificar.error('No se pudo copiar el enlace', `Cópielo a mano: ${url}`, { autoClose: false })
    })
  }

  const verResultados = (campania: CampaniaAssist) => {
    setCampaniaSeleccionada(campania.id)
    openResultados()
  }

  const columns: DataTableColumn<CampaniaAssist>[] = [
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
      accessor: 'fecha_apertura',
      title: 'Ventana',
      width: 180,
      // Las dos fechas a la vista: el estado de abajo se deduce de ellas, y
      // sin verlas no hay forma de entender por qué una campaña sale
      // «Programada» ni hasta cuándo se puede repartir el enlace.
      render: (c) => (
        <Text size="sm">
          {formatFecha(c.fecha_apertura)}
          {c.fecha_cierre ? ` – ${formatFecha(c.fecha_cierre)}` : ' – sin cierre'}
        </Text>
      ),
    },
    {
      accessor: 'estado_campania',
      title: 'Estado',
      width: 120,
      // `estado_campania` y no `activa`: la columna pintaba «Abierta» para una
      // campaña con la apertura en el futuro y para una con el cierre ya
      // pasado, mientras el enlace público rechazaba a quien entraba.
      render: (c) => (
        <StatusBadge tone={TONO_ESTADO_CAMPANIA[c.estado_campania]}>
          {ESTADO_CAMPANIA_LABELS[c.estado_campania]}
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
              // `disabled` y no `hidden`: el enlace existe, lo que no admite
              // respuestas todavía —o ya no— es la campaña. Repartirlo
              // programada o cerrada manda a la gente a un formulario que la
              // rechaza, que es exactamente lo que pasaba cuando la columna
              // Estado decía «Abierta» sin mirar las fechas.
              disabled: campania.estado_campania !== 'abierta',
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
              hidden: campania.estado_campania === 'cerrada' || !puedeGestionar,
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
          Tamizaje anónimo de consumo de sustancias (ASSIST v3.1, OMS/OPS) — Fase 4 del programa de
          prevención de drogas.
        </Text>
        {puedeGestionar && (
          <Button leftSection={<IconPlus size={16} />} onClick={openCrear}>
            Nueva campaña
          </Button>
        )}
      </Group>

      <DataState
        loading={isLoading}
        error={error}
        empty={!campanias.length}
        emptyProps={{
          icon: IconClipboardList,
          title: 'Sin campañas ASSIST',
          description: 'Aún no se ha abierto ninguna campaña de tamizaje.',
        }}
      >
        <SgthTable
          records={campanias}
          columns={columns}
          minHeight={200}
        />
      </DataState>

      <CrearCampaniaAssistModal opened={crearOpened} onClose={closeCrear} />
      <ResultadosAssistModal
        opened={resultadosOpened}
        onClose={() => { setCampaniaSeleccionada(null); closeResultados() }}
        campaniaId={campaniaSeleccionada}
      />
    </Stack>
  )
}
