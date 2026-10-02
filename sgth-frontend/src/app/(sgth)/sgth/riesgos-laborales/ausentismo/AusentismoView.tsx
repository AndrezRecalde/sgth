'use client'

import { useState } from 'react'
import { Group, Button, Text, Alert } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import {
  IconSearch,
  IconFileDownload,
  IconFileTypeCsv,
  IconClipboardList,
  IconInfoCircle,
} from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { useContainedInput } from '@/hooks/useContainedInput'
import { asistenciaService } from '@/features/asistencia/services/asistenciaService'
import { guardarArchivo } from '@/lib/archivo'
import { fromDateValue } from '@/lib/fecha'
import { DataState, notificar, PageHeader, PageShell, SgthTable, Toolbar } from '@/components/ui'
import { getConsolidadoColumns } from '@/features/asistencia/components/consolidado.columns'
import { clavesSso } from '@/features/sso/constants/claves'

const TIPO_ENFERMEDAD = 'enfermedad'

/**
 * Ausentismo por enfermedad (Fase 10): sin backend propio — consume el mismo
 * ConsolidadoPermisoController de Asistencia, fijando tipo='enfermedad'.
 */
export function AusentismoView() {
  // La variante compacta de 40 px: barra de filtros, no formulario de
  // captura (regla 06).
  const compacto = useContainedInput('sm')

  const [fechaInicio, setFechaInicio] = useState<Date | string | null>(null)
  const [fechaFin, setFechaFin] = useState<Date | string | null>(null)
  const [buscar, setBuscar] = useState(false)
  const [exportando, setExportando] = useState<'excel' | 'pdf' | null>(null)

  const params = {
    fecha_inicio: fromDateValue(fechaInicio),
    fecha_fin: fromDateValue(fechaFin),
    tipo: TIPO_ENFERMEDAD,
  }

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: clavesSso.ausentismo.consolidado(params),
    queryFn: () => asistenciaService.consolidado.obtener(params),
    enabled: buscar && !!fechaInicio && !!fechaFin,
    staleTime: 0,
  })

  const consolidado = data?.consolidado ?? []
  const totales = data?.totales
  const canSearch = !!fechaInicio && !!fechaFin

  const handleExportar = async (formato: 'excel' | 'pdf') => {
    if (!canSearch) return
    setExportando(formato)

    const progreso = notificar.proceso(`Exportando ${formato.toUpperCase()}...`, 'Generando el archivo, espere un momento.')

    try {
      const blob = formato === 'excel'
        ? await asistenciaService.consolidado.exportarExcel(params)
        : await asistenciaService.consolidado.exportarPdf(params)

      const ext = formato === 'excel' ? 'csv' : 'pdf'

      // `guardarArchivo` y no las mismas líneas a mano: aquí se revocaba la URL
      // del blob en el acto, justo después del `click()`, y en Firefox eso
      // cancela la descarga.
      guardarArchivo(blob, `ausentismo_enfermedad_${fromDateValue(fechaInicio)}.${ext}`)

      progreso.exito('Archivo descargado', `Consolidado exportado como ${ext.toUpperCase()}.`)
    } catch {
      progreso.error('No se pudo exportar el consolidado de ausentismo', 'Inténtalo de nuevo en unos segundos.')
    } finally {
      setExportando(null)
    }
  }

  return (
    <PageShell>
      <PageHeader
        title="Ausentismo por Enfermedad"
        description="Consolidado de permisos médicos del período, como indicador reactivo de seguridad y salud"
      />

      {/* En `Toolbar` y no en un `Card` con un `Text` que dice «Filtros»: eso
          es exactamente lo que el componente del catálogo resuelve, con la
          variante compacta de 40 px que la regla 06 pide para una barra de
          filtros. */}
      <Toolbar
        actions={
          <Button
            variant="light"
            leftSection={<IconSearch size={16} />}
            disabled={!canSearch}
            loading={isLoading && buscar}
            onClick={() => { setBuscar(true); refetch() }}
          >
            Consultar
          </Button>
        }
      >
        <DatePickerInput
          label="Fecha inicio"
          placeholder="Desde"
          valueFormat="YYYY-MM-DD"
          {...compacto}
          value={fechaInicio}
          onChange={(v) => setFechaInicio(v)}
        />
        <DatePickerInput
          label="Fecha fin"
          placeholder="Hasta"
          valueFormat="YYYY-MM-DD"
          {...compacto}
          value={fechaFin}
          onChange={(v) => setFechaFin(v)}
        />
      </Toolbar>

      {buscar && consolidado.length > 0 && (
        <Group justify="flex-end" gap="sm">
          <Button
            variant="light"
            size="xs"
            leftSection={<IconFileTypeCsv size={14} />}
            loading={exportando === 'excel'}
            onClick={() => handleExportar('excel')}
          >
            Exportar Excel (CSV)
          </Button>
          <Button
            variant="light"
            size="xs"
            leftSection={<IconFileDownload size={14} />}
            loading={exportando === 'pdf'}
            onClick={() => handleExportar('pdf')}
          >
            Exportar PDF
          </Button>
        </Group>
      )}

      {!buscar ? (
        <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light">
          <Text size="sm">Seleccione un rango de fechas y presione Consultar.</Text>
        </Alert>
      ) : (
        <DataState
          loading={isLoading}
          error={error}
          // Antes, un fallo de la consulta se veía igual que un período sin
          // permisos: la pantalla afirmaba que no hubo ausentismo.
          errorTitle="No se pudo cargar el consolidado de ausentismo"
          errorHint="No quiere decir que no haya permisos por enfermedad en el período: no se pudieron consultar."
          onRetry={() => refetch()}
          empty={!consolidado.length}
          emptyProps={{
            icon: IconClipboardList,
            title: 'Sin permisos por enfermedad en el período',
            description: 'No hay permisos médicos registrados entre las fechas seleccionadas. Pruebe con otro rango.',
          }}
        >
          <SgthTable
            idAccessor="servidor_id"
            records={consolidado}
            columns={getConsolidadoColumns(totales)}
          />
        </DataState>
      )}
    </PageShell>
  )
}
