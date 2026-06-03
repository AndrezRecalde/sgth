'use client'

import { useState } from 'react'
import { Stack, Group, Text, Badge, Button,
         Select, Grid, Skeleton } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useQuery } from '@tanstack/react-query'
import { SgthTable } from '@/components/ui/SgthTable'
import { EmptyState } from '@/components/ui/EmptyState'
import { asistenciaService } from '../services/asistenciaService'
import { useServidores } from '@/features/expediente/hooks/useServidores'
import { IconSearch, IconClock } from '@tabler/icons-react'
import type { MarcacionBiometrica, ServidorConRelaciones } from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'

const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const [y, m, d] = v.split('-').map(Number)
  return new Date(y, m - 1, d)
}
const fromDate = (d: any): string => {
  if (!d) return ''
  if (typeof d === 'string') return d.substring(0, 10)
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
}

function formatHora(h?: string | null): string {
  if (!h) return '—'
  return h.substring(0, 5)
}

export function MarcacionesTab() {
  const contained = useContainedInput()
  const [servidorSel, setServidorSel] = useState<string | null>(null)
  const [fechaInicio, setFechaInicio] = useState<Date | null>(null)
  const [fechaFin, setFechaFin]       = useState<Date | null>(null)
  const [buscar, setBuscar]           = useState(false)

  const { data: servidoresData } = useServidores()
  const servidores = (servidoresData?.data ?? []) as ServidorConRelaciones[]

  const servidorOptions = servidores
    .filter(s => s.puede_marcar !== false)
    .map(s => ({
      value: s.cedula ?? '',
      label: `${s.cedula} — ${[s.apellido, s.nombre].filter(Boolean).join(' ')}`,
    }))
    .filter(s => s.value)

  const { data: marcaciones = [], isLoading, refetch } = useQuery({
    queryKey: ['marcaciones', servidorSel, fromDate(fechaInicio), fromDate(fechaFin)],
    queryFn:  () => asistenciaService.marcaciones.listar({
      cedula:       servidorSel!,
      fecha_inicio: fromDate(fechaInicio),
      fecha_fin:    fromDate(fechaFin),
    }),
    enabled:  buscar && !!servidorSel && !!fechaInicio && !!fechaFin,
    staleTime: 0,
  })

  const columns: DataTableColumn<MarcacionBiometrica>[] = [
    {
      accessor: 'Fecha',
      title:    'Fecha',
      width:    110,
      render: ({ Fecha }) => (
        <Text size="sm">
          {new Date(Fecha).toLocaleDateString('es-EC', {
            timeZone: 'UTC',
            day: '2-digit', month: '2-digit', year: 'numeric',
          })}
        </Text>
      ),
    },
    {
      accessor: 'Entrada',
      title:    'Entrada',
      width:    80,
      render: ({ Entrada, HoraEntradaProgramada }) => (
        <Text
          size="sm"
          c={
            Entrada && HoraEntradaProgramada && Entrada > HoraEntradaProgramada
              ? 'orange' : 'inherit'
          }
        >
          {formatHora(Entrada)}
        </Text>
      ),
    },
    {
      accessor: 'AlmuerzoSalida',
      title:    'Sal. Almuerzo',
      width:    100,
      render: ({ AlmuerzoSalida }) => (
        <Text size="sm">{formatHora(AlmuerzoSalida)}</Text>
      ),
    },
    {
      accessor: 'AlmuerzoRetorno',
      title:    'Ret. Almuerzo',
      width:    100,
      render: ({ AlmuerzoRetorno }) => (
        <Text size="sm">{formatHora(AlmuerzoRetorno)}</Text>
      ),
    },
    {
      accessor: 'Salida',
      title:    'Salida',
      width:    80,
      render: ({ Salida }) => (
        <Text size="sm">{formatHora(Salida)}</Text>
      ),
    },
    {
      accessor: 'TipoPermiso',
      title:    'Tipo',
      width:    110,
      render: ({ TipoPermiso }) =>
        TipoPermiso ? (
          <Badge size="xs" color="blue" variant="light">
            {TipoPermiso}
          </Badge>
        ) : null,
    },
  ]

  return (
    <Stack gap="md">
      <Grid>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Select
            label="Servidor (solo con marcación habilitada)"
            placeholder="Buscar servidor"
            data={servidorOptions}
            searchable
            {...contained}
            value={servidorSel}
            onChange={setServidorSel}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 3 }}>
          <DatePickerInput
            label="Fecha inicio"
            placeholder="Desde"
            valueFormat="YYYY-MM-DD"
            {...contained}
            value={fechaInicio}
            onChange={(val: any) => setFechaInicio(val)}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 3 }}>
          <DatePickerInput
            label="Fecha fin"
            placeholder="Hasta"
            valueFormat="YYYY-MM-DD"
            {...contained}
            value={fechaFin}
            onChange={(val: any) => setFechaFin(val)}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 2 }}>
          <Button
            size="sm"
            color="emerald"
            variant="light"
            leftSection={<IconSearch size={16} />}
            disabled={!servidorSel || !fechaInicio || !fechaFin}
            onClick={() => { setBuscar(true); refetch() }}
            mt="lg"
          >
            Consultar
          </Button>
        </Grid.Col>
      </Grid>

      {!buscar ? (
        <EmptyState
          icon={IconClock}
          title="Selecciona un servidor y rango de fechas"
          description="Las marcaciones se consultan desde el sistema biométrico."
        />
      ) : isLoading ? (
        <Skeleton height={200} radius="md" />
      ) : (marcaciones as MarcacionBiometrica[]).length === 0 ? (
        <EmptyState
          icon={IconClock}
          title="Sin marcaciones en el período"
        />
      ) : (
        <SgthTable
          records={marcaciones as MarcacionBiometrica[]}
          columns={columns}
          fetching={false}
          minHeight={200}
        />
      )}
    </Stack>
  )
}
