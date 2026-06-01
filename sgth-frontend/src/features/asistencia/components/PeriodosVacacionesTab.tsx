'use client'

import { useState } from 'react'
import {
  Stack, Group, Button, Text, Badge,
  Select, NumberInput, Alert, Grid,
  Card, Divider, Skeleton,
} from '@mantine/core'
import {
  IconCalendarStats, IconRefresh,
  IconAlertTriangle, IconUsers,
  IconInfoCircle,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useServidores } from '@/features/expediente/hooks/useServidores'
import { usePeriodosVacaciones } from '../hooks/usePeriodosVacaciones'
import { usePeriodosMutations } from '../hooks/usePeriodosMutations'
import { SgthTable } from '@/components/ui/SgthTable'
import type {
  ServidorConRelaciones,
  PeriodoVacacion,
} from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'

const REGIMEN_COLORS = {
  losep:          'blue',
  codigo_trabajo: 'orange',
}
const REGIMEN_LABELS = {
  losep:          'LOSEP',
  codigo_trabajo: 'Código del Trabajo',
}
const ESTADO_COLORS = {
  abierto: 'emerald',
  cerrado: 'gray',
  vencido: 'red',
}

function formatDias(v: number | string | null | undefined): string {
  if (v === null || v === undefined) return '—'
  return Number(v).toFixed(1)
}

export function PeriodosVacacionesTab() {
  const contained = useContainedInput()
  const [servidorSelId, setServidorSelId] =
    useState<number | null>(null)
  const [anio, setAnio] = useState<number>(
    new Date().getFullYear()
  )

  const { data: servidoresData } = useServidores({ per_page: 200 })
  const servidores =
    (servidoresData?.data ?? []) as ServidorConRelaciones[]

  const servidorOptions = servidores.map(s => ({
    value: String(s.id),
    label: `${[s.apellido, s.nombre]
      .filter(Boolean).join(' ')} — ${s.cedula}`,
  }))

  const { data: resumen, isLoading } =
    usePeriodosVacaciones(servidorSelId)

  const { generar, generarTodos } = usePeriodosMutations()

  const periodos = (resumen?.periodos ?? []) as PeriodoVacacion[]
  const saldoTotal   = resumen?.saldo_total   ?? 0
  const alertaLimite = resumen?.alerta_limite ?? false

  const columns: DataTableColumn<PeriodoVacacion>[] = [
    {
      accessor: 'anio',
      title:    'Año',
      width:    70,
      render: ({ anio: a }) => (
        <Text size="sm" fw={700} ff="monospace">{a}</Text>
      ),
    },
    {
      accessor: 'regimen',
      title:    'Régimen',
      width:    130,
      render: ({ regimen }) => (
        <Badge
          color={REGIMEN_COLORS[regimen as keyof typeof REGIMEN_COLORS] ?? 'gray'}
          variant="light" size="sm"
        >
          {REGIMEN_LABELS[regimen as keyof typeof REGIMEN_LABELS] ?? regimen}
        </Badge>
      ),
    },
    {
      accessor: 'anios_antiguedad',
      title:    'Antigüedad',
      width:    90,
      render: ({ anios_antiguedad }) => (
        <Text size="sm" ta="center">
          {anios_antiguedad} años
        </Text>
      ),
    },
    {
      accessor: 'dias_generados',
      title:    'Generados',
      width:    90,
      render: ({ dias_generados }) => (
        <Text size="sm" ta="center" c="blue">
          {formatDias(dias_generados)}
        </Text>
      ),
    },
    {
      accessor: 'dias_utilizados',
      title:    'Utilizados',
      width:    90,
      render: ({ dias_utilizados }) => (
        <Text size="sm" ta="center" c="orange">
          {formatDias(dias_utilizados)}
        </Text>
      ),
    },
    {
      accessor: 'dias_saldo',
      title:    'Saldo',
      width:    90,
      render: ({ dias_saldo }) => (
        <Text size="sm" ta="center" fw={600} c="emerald">
          {formatDias(dias_saldo)}
        </Text>
      ),
    },
    {
      accessor: 'saldo_acumulado',
      title:    'Acumulado',
      width:    100,
      render: ({ saldo_acumulado, regimen }) => {
        const limite   = regimen === 'losep' ? 60 : 999
        const acum     = Number(saldo_acumulado)
        const enAlerta = acum >= 45 && regimen === 'losep'
        return (
          <Group gap={4}>
            <Text
              size="sm" fw={600}
              c={enAlerta ? 'orange' : 'inherit'}
            >
              {formatDias(saldo_acumulado)}
            </Text>
            {enAlerta && (
              <IconAlertTriangle size={12} color="orange" />
            )}
          </Group>
        )
      },
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    90,
      render: ({ estado }) => (
        <Badge
          color={ESTADO_COLORS[estado as keyof typeof ESTADO_COLORS]
            ?? 'gray'}
          variant="light" size="sm"
        >
          {estado}
        </Badge>
      ),
    },
  ]

  return (
    <Stack gap="md">

      {/* ── PANEL SUPERIOR: Generar todos ── */}
      <Card withBorder radius="md" p="md">
        <Group justify="space-between" align="flex-end">
          <Stack gap={4}>
            <Text fw={600} size="sm">
              Generación masiva de períodos
            </Text>
            <Text size="xs" c="dimmed">
              Genera los períodos de vacaciones para todos
              los servidores activos en el año seleccionado.
            </Text>
          </Stack>
          <Group gap="sm" align="flex-end">
            <NumberInput
              label="Año"
              min={2020}
              max={2035}
              {...contained}
              value={anio}
              onChange={(v) =>
                setAnio(typeof v === 'number' ? v : new Date().getFullYear())
              }
              style={{ width: 100 }}
            />
            <Button
              color="emerald"
              variant="filled"
              leftSection={<IconUsers size={16} />}
              loading={generarTodos.isPending}
              onClick={() => {
                if (confirm(
                  `¿Generar períodos ${anio} para todos los servidores activos?`
                )) {
                  generarTodos.mutate(anio)
                }
              }}
            >
              Generar para todos
            </Button>
          </Group>
        </Group>
      </Card>

      <Divider label="Consulta por servidor" labelPosition="left" />

      {/* ── PANEL INFERIOR: Consulta individual ── */}
      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Select
            label="Servidor"
            placeholder="Buscar servidor"
            data={servidorOptions}
            searchable
            {...contained}
            value={servidorSelId ? String(servidorSelId) : null}
            onChange={(v) =>
              setServidorSelId(v ? Number(v) : null)
            }
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 3 }}>
          <Button
            mt={22}
            variant="light"
            color="blue"
            leftSection={<IconRefresh size={16} />}
            disabled={!servidorSelId}
            loading={generar.isPending}
            onClick={() => {
              if (servidorSelId) {
                if (confirm(
                  `¿Generar período ${anio} para este servidor?`
                )) {
                  generar.mutate({
                    servidorId: servidorSelId,
                    anio,
                  })
                }
              }
            }}
          >
            Generar período {anio}
          </Button>
        </Grid.Col>
      </Grid>

      {/* ── RESUMEN SALDO ── */}
      {servidorSelId && !isLoading && resumen && (
        <Alert
          icon={
            alertaLimite
              ? <IconAlertTriangle size={16} />
              : <IconInfoCircle size={16} />
          }
          color={alertaLimite ? 'orange' : 'blue'}
          variant="light"
        >
          <Group gap="sm">
            <Text size="sm">Saldo total disponible:</Text>
            <Badge
              color={alertaLimite ? 'orange' : 'emerald'}
              size="lg"
            >
              {Number(saldoTotal).toFixed(1)} días
            </Badge>
            {alertaLimite && (
              <Text size="xs" c="orange" fw={500}>
                ⚠️ Servidor acumula más de 45 días —
                debe gozar vacaciones pronto (límite LOSEP: 60 días)
              </Text>
            )}
          </Group>
        </Alert>
      )}

      {/* ── TABLA DE PERÍODOS ── */}
      {!servidorSelId ? (
        <Alert
          icon={<IconCalendarStats size={16} />}
          color="gray" variant="light"
        >
          <Text size="sm">
            Selecciona un servidor para ver sus períodos de vacaciones.
          </Text>
        </Alert>
      ) : isLoading ? (
        <Skeleton height={200} radius="md" />
      ) : periodos.length === 0 ? (
        <Alert
          icon={<IconInfoCircle size={16} />}
          color="orange" variant="light"
        >
          <Text size="sm">
            Este servidor no tiene períodos generados.
            Usa el botón "Generar período {anio}" para crearlo.
          </Text>
        </Alert>
      ) : (
        <SgthTable
          records={periodos}
          columns={columns}
          fetching={isLoading}
          minHeight={150}
        />
      )}
    </Stack>
  )
}
