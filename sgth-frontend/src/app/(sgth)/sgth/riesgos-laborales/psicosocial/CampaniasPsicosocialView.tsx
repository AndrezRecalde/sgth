'use client'

import { confirmar, notificar, PageHeader, PageShell, type TableAction } from '@/components/ui'
import { useState } from 'react'
import { Button } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import {
  IconPlus, IconChartBar, IconLink, IconLock, IconClipboardList,
} from '@tabler/icons-react'
import { DataState, SgthTable } from '@/components/ui'
import { useCampaniasPsicosocial, usePsicosocialMutations } from '@/features/sso/hooks/usePsicosocial'
import { CrearCampaniaPsicosocialModal } from '@/features/sso/components/CrearCampaniaPsicosocialModal'
import { ResultadosPsicosocialesModal } from '@/features/sso/components/ResultadosPsicosocialesModal'
import type { CampaniaPsicosocial } from '@/features/sso/services/psicosocialService'
import { columnasCampaniaTamizaje } from '@/features/sso/components/campaniaTamizaje.columns'

export function CampaniasPsicosocialView() {
  const { data: campanias = [], isLoading, error } = useCampaniasPsicosocial()
  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  // Ofrecerlas a quien solo lee serviría para que recibiera un 403.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const { cerrarCampania } = usePsicosocialMutations()
  const [crearOpened, { open: openCrear, close: closeCrear }] = useDisclosure(false)
  const [resultadosOpened, { open: openResultados, close: closeResultados }] = useDisclosure(false)
  const [campaniaSeleccionada, setCampaniaSeleccionada] = useState<number | null>(null)

  const copiarLink = (codigo: string) => {
    const url = `${window.location.origin}${ROUTES.PUBLICO.PSICOSOCIAL(codigo)}`
    navigator.clipboard.writeText(url).then(() => {
      notificar.exito(
        'Enlace copiado',
        'Comparta este enlace con el personal para que responda el cuestionario.',
      )
    }).catch(() => {
      // El portapapeles no está disponible en contexto no seguro y el permiso
      // se puede denegar: sin esto la promesa se rechazaba en silencio y quien
      // pulsaba no sabía si el enlace se había copiado.
      // Sin cierre automático: el enlace hay que poder leerlo para copiarlo.
      notificar.error('No se pudo copiar el enlace', `Cópielo a mano: ${url}`, { autoClose: false })
    })
  }

  const verResultados = (campania: CampaniaPsicosocial) => {
    setCampaniaSeleccionada(campania.id)
    openResultados()
  }

  const accionesDe = (campania: CampaniaPsicosocial): TableAction[] => [
    {
      label: 'Copiar enlace público',
      icon: <IconLink size={14} />,
      // `disabled` y no `hidden`: el enlace existe, lo que no admite
      // respuestas todavía —o ya no— es la campaña. Repartirlo programada o
      // cerrada manda a la gente a un formulario que la rechaza, que es
      // exactamente lo que pasaba cuando la columna Estado decía «Abierta»
      // sin mirar las fechas.
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
  ]

  return (
    <PageShell>
      <PageHeader
        title="Evaluación Psicosocial"
        description="Campañas del cuestionario anónimo del Ministerio del Trabajo (58 ítems) y sus resultados"
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
          title: 'Sin campañas psicosociales',
          description: 'Aún no se ha abierto ninguna campaña de evaluación.',
        }}
      >
        <SgthTable
          records={campanias}
          columns={columnasCampaniaTamizaje(accionesDe)}
          minHeight={200}
        />
      </DataState>

      <CrearCampaniaPsicosocialModal opened={crearOpened} onClose={closeCrear} />
      <ResultadosPsicosocialesModal
        opened={resultadosOpened}
        onClose={() => { setCampaniaSeleccionada(null); closeResultados() }}
        campaniaId={campaniaSeleccionada}
      />
    </PageShell>
  )
}
