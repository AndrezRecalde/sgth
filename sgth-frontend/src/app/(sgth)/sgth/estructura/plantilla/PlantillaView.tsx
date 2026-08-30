'use client'

import { Grid, Group, Progress, Stack, Text } from '@mantine/core'
import {
  IconBriefcase,
  IconUserCheck,
  IconUserQuestion,
  IconFileText,
} from '@tabler/icons-react'
import {
  DataState,
  PageHeader,
  PageShell,
  SectionCard,
  StatCard,
  StatusBadge,
  SgthTable,
} from '@/components/ui'
import { usePlantilla } from '@/features/estructura/hooks/usePlantilla'
import { REGIMEN_LABELS, REGIMEN_TONOS } from '@/lib/regimen'
import type { ResumenPlantilla } from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'

type FilaRegimen = ResumenPlantilla['por_regimen'][number]
type FilaUnidad = ResumenPlantilla['por_unidad'][number]
type FilaModalidad = ResumenPlantilla['por_modalidad'][number]

/** Barra de ocupación: lo lleno frente a lo que falta, en una sola lectura. */
function BarraOcupacion({ ocupadas, plazas }: { ocupadas: number; plazas: number }) {
  const pct = plazas > 0 ? Math.round((ocupadas * 100) / plazas) : 0

  return (
    <Stack gap={4}>
      <Group justify="space-between" gap="xs">
        <Text size="xs" c="dimmed">
          {ocupadas} de {plazas}
        </Text>
        <Text size="xs" fw={600}>
          {pct}%
        </Text>
      </Group>
      <Progress value={pct} size="sm" radius="xl" color="emerald" />
    </Stack>
  )
}

const COLUMNAS_REGIMEN: DataTableColumn<FilaRegimen>[] = [
  {
    accessor: 'regimen',
    title: 'Régimen del puesto',
    render: ({ regimen }) => (
      <StatusBadge tone={REGIMEN_TONOS[regimen] ?? 'neutral'}>
        {REGIMEN_LABELS[regimen] ?? regimen}
      </StatusBadge>
    ),
  },
  { accessor: 'plazas', title: 'Plazas', width: 100, textAlign: 'center' },
  { accessor: 'ocupadas', title: 'Ocupadas', width: 110, textAlign: 'center' },
  {
    accessor: 'vacantes',
    title: 'Vacantes',
    width: 110,
    textAlign: 'center',
    render: ({ vacantes }) => (
      <Text size="sm" fw={600} c={vacantes > 0 ? 'emerald' : undefined}>
        {vacantes}
      </Text>
    ),
  },
  {
    accessor: 'ocupacion',
    title: 'Ocupación',
    width: 180,
    render: (fila) => <BarraOcupacion ocupadas={fila.ocupadas} plazas={fila.plazas} />,
  },
]

const COLUMNAS_UNIDAD: DataTableColumn<FilaUnidad>[] = [
  { accessor: 'unidad', title: 'Unidad administrativa' },
  { accessor: 'plazas', title: 'Plazas', width: 100, textAlign: 'center' },
  { accessor: 'ocupadas', title: 'Ocupadas', width: 110, textAlign: 'center' },
  {
    accessor: 'vacantes',
    title: 'Vacantes',
    width: 110,
    textAlign: 'center',
    render: ({ vacantes }) => (
      <Text size="sm" fw={600} c={vacantes > 0 ? 'emerald' : undefined}>
        {vacantes}
      </Text>
    ),
  },
  {
    accessor: 'ocupacion',
    title: 'Ocupación',
    width: 180,
    render: (fila) => <BarraOcupacion ocupadas={fila.ocupadas} plazas={fila.plazas} />,
  },
]

const COLUMNAS_MODALIDAD: DataTableColumn<FilaModalidad>[] = [
  { accessor: 'etiqueta', title: 'Modalidad de vínculo' },
  {
    accessor: 'total',
    title: 'Personal',
    width: 110,
    textAlign: 'center',
    render: ({ total }) => (
      <Text size="sm" fw={600} c={total === 0 ? 'dimmed' : undefined}>
        {total}
      </Text>
    ),
  },
  {
    accessor: 'ocupa_plaza',
    title: 'Plaza del distributivo',
    width: 200,
    render: ({ ocupa_plaza }) => (
      <StatusBadge tone={ocupa_plaza ? 'info' : 'neutral'}>
        {ocupa_plaza ? 'Ocupa plaza' : 'No ocupa plaza'}
      </StatusBadge>
    ),
  },
]

export function PlantillaView() {
  const { data, isLoading, error } = usePlantilla()

  const plazas = data?.plazas
  const sinPlaza = data?.sin_plaza

  return (
    <PageShell>
      <PageHeader
        title="Plantilla institucional"
        description="Plazas del distributivo, ocupación y personal por modalidad de vínculo"
      />

      <Grid gap="md">
        <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
          <StatCard
            label="Plazas totales"
            value={plazas?.total ?? 0}
            icon={IconBriefcase}
            hint="Puestos activos del distributivo"
            loading={isLoading}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
          <StatCard
            label="Ocupadas"
            value={plazas?.ocupadas ?? 0}
            icon={IconUserCheck}
            hint={plazas ? `${plazas.ocupacion}% de ocupación` : undefined}
            loading={isLoading}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
          <StatCard
            label="Vacantes"
            value={plazas?.vacantes ?? 0}
            icon={IconUserQuestion}
            hint="Plazas disponibles para concurso"
            loading={isLoading}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
          <StatCard
            label="Contratos sin plaza"
            value={
              (sinPlaza?.servicios_ocasionales ?? 0) +
              (sinPlaza?.servicios_profesionales ?? 0)
            }
            icon={IconFileText}
            hint={
              sinPlaza
                ? `${sinPlaza.servicios_ocasionales} ocasionales · ${sinPlaza.servicios_profesionales} profesionales`
                : undefined
            }
            loading={isLoading}
          />
        </Grid.Col>
      </Grid>

      <DataState
        loading={isLoading}
        error={error}
        empty={!isLoading && !data}
        emptyProps={{
          icon: IconBriefcase,
          title: 'Sin datos de plantilla',
          description: 'No hay puestos activos registrados en la estructura.',
        }}
      >
        <Stack gap="lg">
          <SectionCard
            title="Por régimen"
            description="El régimen es del puesto —de su partida—, no de quien lo ocupa."
          >
            <SgthTable
              records={data?.por_regimen ?? []}
              columns={COLUMNAS_REGIMEN}
              idAccessor="regimen"
              minHeight={120}
            />
          </SectionCard>

          <SectionCard
            title="Por modalidad de vínculo"
            description={
              sinPlaza
                ? `${sinPlaza.total_vigentes} contratos vigentes. Los ocasionales son el ${sinPlaza.porcentaje_ocasionales}% del total.`
                : undefined
            }
          >
            <SgthTable
              records={data?.por_modalidad ?? []}
              columns={COLUMNAS_MODALIDAD}
              idAccessor="tipo_nombramiento"
              minHeight={120}
            />
          </SectionCard>

          <SectionCard
            title="Por unidad administrativa"
            description="Ordenado por vacantes: primero lo que falta por llenar."
          >
            <SgthTable
              records={data?.por_unidad ?? []}
              columns={COLUMNAS_UNIDAD}
              idAccessor="unidad"
              minHeight={120}
            />
          </SectionCard>
        </Stack>
      </DataState>
    </PageShell>
  )
}
