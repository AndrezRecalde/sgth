'use client'

import React, { useState } from 'react'
import {
  Stack, Group, Button, Text, Badge, Grid, Card, Skeleton, Alert, Table,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import {
  IconSearch, IconFileDownload, IconFileTypeCsv, IconClipboardList, IconInfoCircle, IconCheck,
} from '@tabler/icons-react'
import { notifications } from '@mantine/notifications'
import { useQuery } from '@tanstack/react-query'
import { useContainedInput } from '@/hooks/useContainedInput'
import { asistenciaService } from '@/features/asistencia/services/asistenciaService'
import { fromDateValue } from '@/lib/fecha'
import type { ConsolidadoPermiso } from '@/types/api'

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

    const notifId = `export-ausentismo-${formato}-${Date.now()}`
    notifications.show({
      id: notifId,
      title: `Exportando ${formato.toUpperCase()}...`,
      message: 'Generando el archivo, espere un momento.',
      color: 'blue',
      loading: true,
      autoClose: false,
      withCloseButton: false,
    })

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

      notifications.update({
        id: notifId,
        title: 'Archivo descargado',
        message: `Consolidado exportado como ${ext.toUpperCase()}.`,
        color: 'emerald',
        loading: false,
        autoClose: 3000,
        withCloseButton: true,
        icon: React.createElement(IconCheck, { size: 16 }),
      })
    } catch {
      notifications.update({
        id: notifId,
        title: 'Error',
        message: 'No se pudo exportar el consolidado.',
        color: 'red',
        loading: false,
        autoClose: 3000,
        withCloseButton: true,
      })
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
        <Card withBorder radius="md" p={0}>
          <Table striped highlightOnHover withTableBorder>
            <Table.Thead>
              <Table.Tr>
                <Table.Th>Cédula</Table.Th>
                <Table.Th>Servidor</Table.Th>
                <Table.Th>Unidad</Table.Th>
                <Table.Th ta="center">Permisos</Table.Th>
                <Table.Th ta="right">Minutos</Table.Th>
                <Table.Th ta="right">Tiempo</Table.Th>
                <Table.Th ta="right">Días</Table.Th>
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {(consolidado as ConsolidadoPermiso[]).map((fila) => (
                <Table.Tr key={fila.servidor_id}>
                  <Table.Td><Text size="sm" ff="monospace">{fila.cedula}</Text></Table.Td>
                  <Table.Td><Text size="sm" fw={500}>{fila.servidor_nombre}</Text></Table.Td>
                  <Table.Td><Text size="sm" c="dimmed">{fila.unidad}</Text></Table.Td>
                  <Table.Td ta="center">
                    <Badge variant="light" color="blue" size="sm">{fila.total_permisos}</Badge>
                  </Table.Td>
                  <Table.Td ta="right"><Text size="sm" ff="monospace">{fila.total_minutos}</Text></Table.Td>
                  <Table.Td ta="right"><Text size="sm" ff="monospace" fw={500}>{fila.tiempo_total}</Text></Table.Td>
                  <Table.Td ta="right">
                    <Text size="sm" ff="monospace" fw={600} c={fila.total_dias >= 1 ? 'orange' : 'inherit'}>
                      {fila.total_dias.toFixed(2)}
                    </Text>
                  </Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
            {totales && (
              <Table.Tfoot>
                <Table.Tr style={{ backgroundColor: 'var(--mantine-color-green-0)' }}>
                  <Table.Td colSpan={3}><Text size="sm" fw={700}>TOTALES</Text></Table.Td>
                  <Table.Td ta="center"><Text size="sm" fw={700}>{totales.total_permisos}</Text></Table.Td>
                  <Table.Td ta="right"><Text size="sm" fw={700} ff="monospace">{totales.total_minutos}</Text></Table.Td>
                  <Table.Td ta="right"><Text size="sm" fw={700}>—</Text></Table.Td>
                  <Table.Td ta="right"><Text size="sm" fw={700} ff="monospace">{totales.total_dias.toFixed(2)}</Text></Table.Td>
                </Table.Tr>
              </Table.Tfoot>
            )}
          </Table>
        </Card>
      )}
    </Stack>
  )
}
