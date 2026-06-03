'use client'

import {
  Select, Grid, Switch, NumberInput,
  Box, LoadingOverlay,
} from '@mantine/core'
import { useForm, Controller, useWatch, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useUnidades } from '../hooks/useUnidades'
import { useGruposOcupacionales } from '../hooks/useGruposOcupacionales'
import { useCargos } from '../hooks/useCargos'
import { puestoSchema, type PuestoFormData } from '../schemas/puesto.schema'
import type { UnidadConRelaciones, Cargo, GrupoOcupacional } from '@/types/api'

const ROL_OPTIONS = [
  { value: 'dignatario',               label: 'Dignatario' },
  { value: 'ejecucion_coordinacion',   label: 'Ejecución y Coordinación' },
  { value: 'ejecucion_procesos',       label: 'Ejecución de Procesos' },
  { value: 'ejecucion_procesos_apoyo', label: 'Ejecución de Procesos de Apoyo' },
  { value: 'administrativo',           label: 'Administrativo' },
  { value: 'codigo_trabajo',           label: 'Código del Trabajo' },
]

const COMPLEJIDAD_OPTIONS = [
  { value: 'bajo',  label: 'Nivel Bajo' },
  { value: 'medio', label: 'Nivel Medio' },
  { value: 'alto',  label: 'Nivel Alto' },
]

const REGIMEN_OPTIONS = [
  { value: 'losep',          label: 'LOSEP' },
  { value: 'codigo_trabajo', label: 'Código del Trabajo' },
]

interface Props {
  initialValues?: Partial<PuestoFormData>
  onSubmit: (values: PuestoFormData) => void
}

export function PuestoForm({ initialValues, onSubmit }: Props) {
  const contained = useContainedInput()
  const { data: unidadesRaw } = useUnidades({ nivel: 2 })
  const { data: gruposRaw }   = useGruposOcupacionales()
  const { data: cargosRaw }   = useCargos()

  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[]
  const grupos   = (gruposRaw   ?? []) as GrupoOcupacional[]
  const cargos   = (cargosRaw   ?? []) as Cargo[]

  const isReady = Array.isArray(unidadesRaw) &&
                  Array.isArray(gruposRaw)   &&
                  Array.isArray(cargosRaw)

  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<PuestoFormData>({
    resolver: zodResolver(puestoSchema) as Resolver<PuestoFormData>,
    defaultValues: {
      cargo_id:                  initialValues?.cargo_id                  ?? undefined,
      unidad_administrativa_id:  initialValues?.unidad_administrativa_id  ?? undefined,
      grupo_ocupacional_id:      initialValues?.grupo_ocupacional_id      ?? null,
      partida_presupuestaria_id: initialValues?.partida_presupuestaria_id ?? null,
      plazas:                    initialValues?.plazas                    ?? 1,
      rol_puesto:                initialValues?.rol_puesto                ?? null,
      nivel_complejidad:         initialValues?.nivel_complejidad         ?? null,
      regimen_laboral:           initialValues?.regimen_laboral           ?? 'losep',
      es_jefe:                   initialValues?.es_jefe                   ?? false,
      activo:                    initialValues?.activo                    ?? true,
    },
  })

  const regimenActual = useWatch({
    control,
    name: 'regimen_laboral',
    defaultValue: 'losep',
  })

  const cargoOptions = cargos.map(c => ({
    value: String(c.id),
    label: c.nombre ?? '',
  }))

  const unidadOptions = unidades.map(u => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }))

  type GrupoItem = { id: number; grado_codigo?: string; grupo?: string; rmu?: string | number }
  const grupoOptions = ((grupos ?? []) as GrupoItem[]).map(g => ({
    value: String(g.id),
    label: `${g.grado_codigo ?? ''} — ${g.grupo ?? ''} ($${g.rmu ?? 0})`,
  }))

  return (
    <Box style={{ position: 'relative' }}>
      <LoadingOverlay visible={!isReady} zIndex={10} />
      <form id="puesto-form" onSubmit={handleSubmit(onSubmit)}>
        <Grid>
          <Grid.Col span={{ base: 12, sm: 8 }}>
            <Controller
              name="cargo_id"
              control={control}
              render={({ field }) => (
                <Select
                  label="Cargo"
                  placeholder="Seleccionar cargo"
                  data={cargoOptions}
                  searchable
                  {...contained}
                  value={field.value ? String(field.value) : ''}
                  onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                  error={errors.cargo_id?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <Controller
              name="regimen_laboral"
              control={control}
              render={({ field }) => (
                <Select
                  label="Régimen laboral"
                  data={REGIMEN_OPTIONS}
                  {...contained}
                  value={field.value ?? 'losep'}
                  onChange={(v) =>
                    field.onChange((v ?? 'losep') as 'losep' | 'codigo_trabajo')
                  }
                  error={errors.regimen_laboral?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={12}>
            <Controller
              name="unidad_administrativa_id"
              control={control}
              render={({ field }) => (
                <Select
                  label="Unidad administrativa"
                  placeholder="Seleccionar gestión"
                  data={unidadOptions}
                  searchable
                  {...contained}
                  value={field.value ? String(field.value) : ''}
                  onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                  error={errors.unidad_administrativa_id?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={12}>
            <Controller
              name="grupo_ocupacional_id"
              control={control}
              render={({ field }) => (
                <Select
                  label={regimenActual === 'losep'
                    ? 'Grupo ocupacional (LOSEP)'
                    : 'Grupo ocupacional (CT — referencial)'}
                  placeholder="Seleccionar grupo"
                  data={grupoOptions}
                  searchable
                  clearable
                  {...contained}
                  value={field.value ? String(field.value) : ''}
                  onChange={(v) => field.onChange(v ? Number(v) : null)}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <Controller
              name="plazas"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Plazas"
                  placeholder="1"
                  min={1}
                  {...contained}
                  value={field.value ?? 1}
                  onChange={(v) => field.onChange(typeof v === 'number' ? v : 1)}
                  error={errors.plazas?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <Controller
              name="nivel_complejidad"
              control={control}
              render={({ field }) => (
                <Select
                  label="Complejidad"
                  placeholder="Seleccionar"
                  data={COMPLEJIDAD_OPTIONS}
                  clearable
                  {...contained}
                  value={field.value ?? ''}
                  onChange={(v) =>
                    field.onChange((v as PuestoFormData['nivel_complejidad']) ?? null)
                  }
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <Controller
              name="rol_puesto"
              control={control}
              render={({ field }) => (
                <Select
                  label="Rol del puesto"
                  placeholder="Seleccionar rol"
                  data={ROL_OPTIONS}
                  clearable
                  {...contained}
                  value={field.value ?? ''}
                  onChange={(v) =>
                    field.onChange((v as PuestoFormData['rol_puesto']) ?? null)
                  }
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="es_jefe"
              control={control}
              render={({ field }) => (
                <Switch
                  label="Es jefe de unidad"
                  checked={field.value}
                  onChange={(e) => field.onChange(e.currentTarget.checked)}
                  color="emerald"
                  mt="xs"
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="activo"
              control={control}
              render={({ field }) => (
                <Switch
                  label="Puesto activo"
                  checked={field.value}
                  onChange={(e) => field.onChange(e.currentTarget.checked)}
                  color="emerald"
                  mt="xs"
                />
              )}
            />
          </Grid.Col>
        </Grid>
      </form>
    </Box>
  )
}
