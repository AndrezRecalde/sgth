'use client'

import { useState } from 'react'
import { Stack, Grid, Group, Button, Text } from '@mantine/core'
import { CountBadge, DataState, SgthModal, SgthTable } from '@/components/ui'
import { DatePickerInput } from '@mantine/dates'
import { IconSearch, IconTruckDelivery } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarPuestoSelect } from '@/features/estructura/components/BuscarPuestoSelect'
import { useReporteEppEntregas } from '../hooks/useEppEntregas'
import { toDateValue, fromDateValue } from '@/lib/fecha'
import type { ReporteEppFila } from '../services/tipos'
import type { DataTableColumn } from 'mantine-datatable'

interface Props {
  opened: boolean
  onClose: () => void
}

export function ReporteEppModal({ opened, onClose }: Props) {
  const contained = useContainedInput()

  const [fechaInicio, setFechaInicio] = useState('')
  const [fechaFin, setFechaFin] = useState('')
  const [puestoId, setPuestoId] = useState<number | null>(null)
  const [filtros, setFiltros] = useState<{ fecha_inicio: string; fecha_fin: string; puesto_id?: number } | null>(null)

  const { data: reporte, isLoading, error, refetch } = useReporteEppEntregas(filtros)

  const handleBuscar = () => {
    if (!fechaInicio || !fechaFin) return
    setFiltros({ fecha_inicio: fechaInicio, fecha_fin: fechaFin, puesto_id: puestoId ?? undefined })
  }

  const columns: DataTableColumn<ReporteEppFila>[] = [
    { accessor: 'servidor_nombre', title: 'Servidor' },
    { accessor: 'puesto', title: 'Puesto' },
    {
      accessor: 'total_entregas',
      title: 'Entregas',
      render: (f) => <CountBadge>{f.total_entregas}</CountBadge>,
    },
    {
      accessor: 'total_devoluciones',
      title: 'Devoluciones',
      render: (f) => <CountBadge>{f.total_devoluciones}</CountBadge>,
    },
    {
      accessor: 'total_reposiciones',
      title: 'Reposiciones',
      render: (f) => <CountBadge>{f.total_reposiciones}</CountBadge>,
    },
  ]

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title="Lista de EPP entregados"
      size="xl"
    >
      <Stack gap="md">
        {/* El puesto se lleva la fila entera: su etiqueta es el cargo más
            la unidad —«Operador de maquinaria pesada — Dirección de Obras
            Públicas»— y en la fila de filtros tenía 154 px de 392. */}
        <Grid gap="sm">
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <DatePickerInput
              label="Desde"
              placeholder="Seleccionar"
              valueFormat="DD/MM/YYYY"
              {...contained}
              value={toDateValue(fechaInicio)}
              onChange={(d) => setFechaInicio(fromDateValue(d ?? null))}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <DatePickerInput
              label="Hasta"
              placeholder="Seleccionar"
              valueFormat="DD/MM/YYYY"
              {...contained}
              value={toDateValue(fechaFin)}
              onChange={(d) => setFechaFin(fromDateValue(d ?? null))}
            />
          </Grid.Col>
          <Grid.Col span={12}>
            <BuscarPuestoSelect
              label="Puesto (opcional)"
              value={puestoId}
              onChange={setPuestoId}
            />
          </Grid.Col>
          <Grid.Col span={12}>
            <Group justify="flex-end">
              <Button
                h={48}
                leftSection={<IconSearch size={16} />}
                onClick={handleBuscar}
                disabled={!fechaInicio || !fechaFin}
              >
                Buscar
              </Button>
            </Group>
          </Grid.Col>
        </Grid>

        {filtros && (
          <DataState
            loading={isLoading}
            error={error}
            errorTitle="No se pudo generar el reporte de EPP entregados"
            errorHint="No quiere decir que no haya movimientos en el período: no se pudieron consultar."
            onRetry={() => refetch()}
            skeletonRows={4}
            empty={!reporte?.consolidado.length}
            emptyProps={{
              icon: IconTruckDelivery,
              title: 'Sin movimientos de EPP en el período',
              description: 'No hay entregas, devoluciones ni reposiciones entre las fechas seleccionadas.',
            }}
          >
            {reporte && (
              <>
                <Group gap="lg">
                  <Text size="sm">
                    Total registros: <Text span fw={600}>{reporte.totales.total_registros}</Text>
                  </Text>
                  <Text size="sm">
                    Servidores: <Text span fw={600}>{reporte.totales.total_servidores}</Text>
                  </Text>
                </Group>

                <SgthTable
                  records={reporte.consolidado}
                  columns={columns}
                  idAccessor="servidor_id"
                  minHeight={120}
                />
              </>
            )}
          </DataState>
        )}
      </Stack>
    </SgthModal>
  )
}
