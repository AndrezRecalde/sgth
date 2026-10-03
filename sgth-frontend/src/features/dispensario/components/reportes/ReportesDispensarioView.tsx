'use client'

import { useState } from 'react'
import { Alert, Grid, Stack, Text } from '@mantine/core'
import { IconInfoCircle, IconReportAnalytics } from '@tabler/icons-react'
import { DataState } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { hoyIso } from '@/lib/fecha'
import {
  useCatalogoReportes, useDescargarReporte, useReporte,
} from '../../hooks/useReportesDispensario'
import type { FiltrosReporteDispensario } from '../../services/reportesDispensarioService'
import { ListaReportes } from './ListaReportes'
import { FiltrosReporte } from './FiltrosReporte'
import { ResultadoReporte } from './ResultadoReporte'

/** Del primero del mes a hoy: lo que se pide casi siempre. */
function filtrosIniciales(): FiltrosReporteDispensario {
  const hoy = hoyIso()
  return { desde: `${hoy.slice(0, 8)}01`, hasta: hoy }
}

const AVISOS = {
  propio:    'Estos reportes muestran solo sus atenciones.',
  autoridad: 'Ve los reportes agregados. Los que llevan nombres de pacientes con diagnósticos se quedan en el Dispensario.',
} as const

/**
 * Reportes del Dispensario: el catálogo a la izquierda, y a la derecha los
 * filtros, la vista previa y la descarga. Qué reportes aparecen y con qué
 * alcance lo decide el backend según el rol.
 */
export function ReportesDispensarioView() {
  const catalogo = useCatalogoReportes()
  const [elegido, setElegido] = useState<string | null>(null)
  const [filtros, setFiltros] = useState(filtrosIniciales)
  const [consultados, setConsultados] = useState<FiltrosReporteDispensario | null>(null)
  const descargar = useDescargarReporte()

  const reportes = catalogo.data?.reportes ?? []
  const clave = elegido ?? reportes[0]?.clave ?? null
  const reporte = reportes.find((r) => r.clave === clave)
  const resultado = useReporte(consultados ? clave : null, consultados)

  // Otro reporte, otra consulta: lo de antes ya no corresponde a lo elegido.
  const elegir = (nueva: string) => {
    setElegido(nueva)
    setConsultados(null)
    // Cada reporte agrupa a su manera: arranca con la primera suya.
    const agrupaciones = reportes.find((r) => r.clave === nueva)?.agrupaciones ?? []
    setFiltros((f) => ({ ...f, agrupacion: agrupaciones[0]?.value }))
  }

  const aviso = catalogo.data?.propio
    ? AVISOS.propio
    : catalogo.data?.alcance === 'autoridad' ? AVISOS.autoridad : null

  return (
    <DataState
      loading={catalogo.isLoading}
      error={catalogo.error}
      errorTitle="No se pudo cargar el catálogo de reportes"
      onRetry={() => catalogo.refetch()}
      empty={!reportes.length}
      emptyProps={{ icon: IconReportAnalytics, title: 'No hay reportes para su rol' }}
    >
      <Grid gap="lg">
        <Grid.Col span={{ base: 12, md: 3 }}>
          <ListaReportes reportes={reportes} activo={clave} onElegir={elegir} />
        </Grid.Col>

        <Grid.Col span={{ base: 12, md: 9 }}>
          <Stack gap="md">
            {aviso && (
              <Alert icon={<IconInfoCircle size={16} />} variant="light" color={SEMANTIC_COLOR.info}>
                <Text size="sm">{aviso}</Text>
              </Alert>
            )}

            {reporte && catalogo.data && (
              <FiltrosReporte
                filtros={filtros}
                reporte={reporte}
                opciones={catalogo.data.opciones}
                onCambiar={(cambio) => setFiltros((f) => ({ ...f, ...cambio }))}
                onConsultar={() => setConsultados({ ...filtros })}
                consultando={resultado.isFetching}
                onDescargar={() => clave && consultados && descargar.mutate({ clave, filtros: consultados })}
                descargando={descargar.isPending}
                puedeDescargar={!!consultados && !!resultado.data?.total}
              />
            )}

            <ResultadoReporte
              consultado={!!consultados}
              cargando={resultado.isLoading}
              error={resultado.error}
              resultado={resultado.data}
              onReintentar={() => resultado.refetch()}
            />
          </Stack>
        </Grid.Col>
      </Grid>
    </DataState>
  )
}
