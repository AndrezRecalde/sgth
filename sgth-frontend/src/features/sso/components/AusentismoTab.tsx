'use client'

import React, { useState } from 'react'
import {
  Stack, Group, Button, Text, Grid, Card, Skeleton, Alert,
} from '@mantine/core'
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
import { fromDateValue } from '@/lib/fecha'
import { notificar, SgthTable } from '@/components/ui'
import { getConsolidadoColumns } from '@/features/asistencia/components/consolidado.columns'

const TIPO_ENFERMEDAD = 'enfermedad'

/**
 * Ausentismo por enfermedad (Fase 10): sin backend propio — consume el mismo
 * ConsolidadoPermisoController de Asistencia, fijando tipo='enfermedad'.
 */
export function AusentismoTab() {
  const contained = useContainedInput()

  const [fechaInicio, setFechaInicio] = useState<Date | string | null>(null)
  const [fechaFin, setFechaFin] = useState<Date | string | null>(null)
  const [buscar, setBuscar] = useState(false)
  const [exportando, setExportando] = useState<'excel' | 'pdf' | null>(null)

  const params = {
    fecha_inicio: fromDateValue(fechaInicio),
    fecha_fin: fromDateValue(fechaFin),
    tipo: TIPO_ENFERMEDAD,
  }

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['sso-ausentismo-enfermedad', params],
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
      const filename = `ausentismo_enfermedad_${fromDateValue(fechaInicio)}.${ext}`
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = filename
      link.click()
      URL.revokeObjectURL(url)

      progreso.exito('Archivo descargado', `Consolidado exportado como ${ext.toUpperCase()}.`)
    } catch {
      progreso.error('Error', 'No se pudo exportar el consolidado.')
    } finally {
      setExportando(null)
    }
  }

  return (
    <Stack gap="md">
      <Text size="sm" c="dimmed">
        Ausentismo por enfermedad — consolidado de permisos médicos del módulo de Asistencia,
        filtrado a permisos de tipo &quot;enfermedad&quot;. Indicador reactivo de Seguridad y Salud Ocupacional.
      </Text>

      <Card withBorder radius="md" p="md">
        <Text fw={600} size="sm" mb="sm">Filtros</Text>
        <Grid>
          <Grid.Col span={{ base: 12, sm: 5 }}>
            <DatePickerInput
              label="Fecha inicio"
              placeholder="Desde"
              valueFormat="YYYY-MM-DD"
              {...contained}
              value={fechaInicio}
              onChange={(v) => setFechaInicio(v)}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 5 }}>
            <DatePickerInput
              label="Fecha fin"
              placeholder="Hasta"
              valueFormat="YYYY-MM-DD"
              {...contained}
              value={fechaFin}
              onChange={(v) => setFechaFin(v)}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 2 }} style={{ display: 'flex', alignItems: 'flex-end' }}>
            <Button
              color="emerald"
              variant="light"
              leftSection={<IconSearch size={16} />}
              disabled={!canSearch}
              loading={isLoading && buscar}
              onClick={() => { setBuscar(true); refetch() }}
              fullWidth
            >
              Consultar
            </Button>
          </Grid.Col>
        </Grid>
      </Card>

      {buscar && consolidado.length > 0 && (
        <Group justify="flex-end" gap="sm">
          <Button
            variant="light"
            color="blue"
            size="xs"
            leftSection={<IconFileTypeCsv size={14} />}
            loading={exportando === 'excel'}
            onClick={() => handleExportar('excel')}
          >
            Exportar Excel (CSV)
          </Button>
          <Button
            variant="light"
            color="red"
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
        <Alert icon={<IconInfoCircle size={16} />} color="blue" variant="light">
          <Text size="sm">Seleccione un rango de fechas y presione Consultar.</Text>
        </Alert>
      ) : isLoading ? (
        <Skeleton height={200} radius="md" />
      ) : consolidado.length === 0 ? (
        <Alert icon={<IconClipboardList size={16} />} color="gray" variant="light">
          <Text size="sm">Sin permisos por enfermedad registrados en el período seleccionado.</Text>
        </Alert>
      ) : (
        <SgthTable
          idAccessor="servidor_id"
          records={consolidado}
          columns={getConsolidadoColumns(totales)}
        />
      )}
    </Stack>
  )
}
