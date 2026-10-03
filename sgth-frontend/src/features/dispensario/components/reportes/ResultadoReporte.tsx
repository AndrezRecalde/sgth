'use client'

import { Stack, Text } from '@mantine/core'
import { IconReportAnalytics, IconSearch } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { DataState, EmptyState, SgthTable } from '@/components/ui'
import type { CeldaReporte, ResultadoReporte as Resultado } from '../../services/reportesDispensarioService'

interface Props {
  consultado: boolean
  cargando:   boolean
  error:      unknown
  resultado:  Resultado | undefined
  onReintentar: () => void
}

type Fila = Record<string, CeldaReporte> & { __fila: number }

/** Los números se alinean a la derecha: así se comparan de un vistazo. */
function columnas(resultado: Resultado): DataTableColumn<Fila>[] {
  const primera = resultado.filas[0] ?? {}
  return resultado.columnas.map((c) => ({
    accessor: c.clave,
    title:    c.titulo,
    textAlign: typeof primera[c.clave] === 'number' ? 'right' : 'left',
    render:   (fila) => fila[c.clave] ?? '—',
  }))
}

/** La vista previa del reporte: lo que va a salir en el Excel. */
export function ResultadoReporte({ consultado, cargando, error, resultado, onReintentar }: Props) {
  if (!consultado) {
    return (
      <EmptyState
        icon={IconSearch}
        title="Elija el período y pulse Consultar"
        description="Verá aquí lo que va a salir en el archivo antes de descargarlo."
      />
    )
  }

  const filas = resultado?.filas ?? []

  return (
    <DataState
      loading={cargando}
      error={error}
      errorTitle="No se pudo generar el reporte"
      onRetry={onReintentar}
      empty={!filas.length}
      emptyProps={{
        icon: IconReportAnalytics,
        title: 'Sin datos en este período',
        description: 'Pruebe con otras fechas o quite algún filtro.',
      }}
    >
      {resultado && (
        <Stack gap="xs">
          <Text size="xs" c="dimmed">
            {resultado.recortado
              ? `Se muestran las primeras ${filas.length} de ${resultado.total} filas; el Excel lleva todas.`
              : `${resultado.total} ${resultado.total === 1 ? 'fila' : 'filas'}.`}
          </Text>
          <SgthTable<Fila>
            records={filas.map((f, i) => ({ ...f, __fila: i }))}
            idAccessor="__fila"
            columns={columnas(resultado)}
            minHeight={200}
          />
        </Stack>
      )}
    </DataState>
  )
}
