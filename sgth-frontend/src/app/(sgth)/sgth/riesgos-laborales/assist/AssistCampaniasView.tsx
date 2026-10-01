'use client'

import { confirmar, notificar, PageHeader, PageShell } from '@/components/ui'
import { useState } from 'react'
import { Button, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import {
  IconPlus, IconChartBar, IconLink, IconLock, IconClipboardList,
} from '@tabler/icons-react'
import { DataState, SgthTable, StatusBadge, TableActions } from '@/components/ui'
import { useCampaniasAssist, useAssistMutations } from '@/features/sso/hooks/useAssist'
import { CrearCampaniaAssistModal } from '@/features/sso/components/CrearCampaniaAssistModal'
import { ResultadosAssistModal } from '@/features/sso/components/ResultadosAssistModal'
import type { CampaniaAssist } from '@/features/sso/services/assistService'
import type { DataTableColumn } from 'mantine-datatable'

export function AssistCampaniasView() {
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
              hidden: !campania.activa || !puedeGestionar,
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
    <PageShell>
      <PageHeader
        title="Tamizaje ASSIST"
        description="Campañas de tamizaje anónimo de consumo de sustancias (ASSIST v3.1, OMS/OPS) y sus resultados"
        // El texto que describía la pantalla vivía en un `Text` suelto sobre
        // la tabla; su sitio es la descripción de la cabecera. Y el botón
        // `variant="light"`, como el resto de acciones principales del
        // sistema: era el único del módulo en `filled` (regla 06).
        actions={puedeGestionar ? (
        <Button leftSection={<IconPlus size={16} />} variant="light" onClick={openCrear}>
          Nueva campaña
        </Button>
        ) : undefined}
      />

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
    </PageShell>
  )
}
