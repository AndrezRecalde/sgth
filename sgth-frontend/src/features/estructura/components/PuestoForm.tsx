'use client'

import {
  TextInput, Select, NumberInput,
  Grid, Switch, Textarea,
} from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useUnidades } from '../hooks/useUnidades'
import { useGruposOcupacionales } from '../hooks/useGruposOcupacionales'
import { puestoSchema, type PuestoFormData } from '../schemas/puesto.schema'
import type { UnidadConRelaciones } from '@/types/api'

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

  // Solo unidades de nivel 2 (gestiones principales)
  const { data: unidades = [] } = useUnidades({ nivel: 2 })
  const { data: grupos = [] }   = useGruposOcupacionales()

  const form = useForm<PuestoFormData>({
    initialValues: {
      denominacion:              initialValues?.denominacion ?? '',
      mision:                    initialValues?.mision ?? null,
      unidad_administrativa_id:  initialValues?.unidad_administrativa_id
        ?? ('' as unknown as number),
      grupo_ocupacional_id:      initialValues?.grupo_ocupacional_id ?? null,
      partida_presupuestaria_id: initialValues?.partida_presupuestaria_id ?? null,
      plazas:                    initialValues?.plazas ?? 1,
      rol_puesto:                initialValues?.rol_puesto ?? null,
      nivel_complejidad:         initialValues?.nivel_complejidad ?? null,
      regimen_laboral:           initialValues?.regimen_laboral ?? 'losep',
      es_jefe:                   initialValues?.es_jefe ?? false,
      activo:                    initialValues?.activo ?? true,
    },
    validate: zodResolver(puestoSchema),
  })

  const unidadOptions = (unidades as unknown as UnidadConRelaciones[]).map(u => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }))

  type GrupoItem = {
    id: number
    grado_codigo?: string
    grupo?: string
    rmu?: number
    regimen?: string
  }
  const grupoOptions = (grupos as GrupoItem[]).map(g => ({
    value: String(g.id),
    label: `${g.grado_codigo ?? ''} — ${g.grupo ?? ''} ($${g.rmu ?? 0})`,
  }))

  const regimenActual = form.values.regimen_laboral

  return (
    <form id="puesto-form" onSubmit={form.onSubmit(onSubmit)}>
      <Grid>
        <Grid.Col span={{ base: 12, sm: 8 }}>
          <TextInput
            label="Denominación del puesto"
            placeholder="Ej: Analista de Talento Humano"
            {...contained}
            {...form.getInputProps('denominacion')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Select
            label="Régimen laboral"
            data={REGIMEN_OPTIONS}
            {...contained}
            value={form.values.regimen_laboral}
            onChange={(v) =>
              form.setFieldValue('regimen_laboral',
                (v ?? 'losep') as 'losep' | 'codigo_trabajo')
            }
            error={form.errors.regimen_laboral}
          />
        </Grid.Col>
        <Grid.Col span={12}>
          <Select
            label="Unidad administrativa"
            placeholder="Seleccionar gestión"
            data={unidadOptions}
            searchable
            {...contained}
            value={form.values.unidad_administrativa_id
              ? String(form.values.unidad_administrativa_id) : ''}
            onChange={(v) =>
              form.setFieldValue('unidad_administrativa_id',
                v ? Number(v) : ('' as unknown as number))
            }
            error={form.errors.unidad_administrativa_id}
          />
        </Grid.Col>
        <Grid.Col span={12}>
          <Select
            label={regimenActual === 'losep'
              ? 'Grupo ocupacional (LOSEP)'
              : 'Grupo ocupacional (CT — referencial)'}
            placeholder="Seleccionar grupo"
            data={grupoOptions}
            searchable
            clearable
            {...contained}
            value={form.values.grupo_ocupacional_id
              ? String(form.values.grupo_ocupacional_id) : ''}
            onChange={(v) =>
              form.setFieldValue('grupo_ocupacional_id',
                v ? Number(v) : null)
            }
            error={form.errors.grupo_ocupacional_id}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <NumberInput
            label="Plazas"
            placeholder="1"
            min={1}
            {...contained}
            value={form.values.plazas}
            onChange={(v) =>
              form.setFieldValue('plazas', typeof v === 'number' ? v : 1)
            }
            error={form.errors.plazas}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Select
            label="Complejidad"
            placeholder="Seleccionar"
            data={COMPLEJIDAD_OPTIONS}
            clearable
            {...contained}
            value={form.values.nivel_complejidad ?? ''}
            onChange={(v) =>
              form.setFieldValue('nivel_complejidad',
                (v as PuestoFormData['nivel_complejidad']) ?? null)
            }
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Select
            label="Rol del puesto"
            placeholder="Seleccionar rol"
            data={ROL_OPTIONS}
            clearable
            {...contained}
            value={form.values.rol_puesto ?? ''}
            onChange={(v) =>
              form.setFieldValue('rol_puesto',
                (v as PuestoFormData['rol_puesto']) ?? null)
            }
          />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea
            label="Misión del puesto"
            placeholder="Describa la misión del puesto (opcional)"
            rows={3}
            {...contained}
            value={form.values.mision ?? ''}
            onChange={(e) =>
              form.setFieldValue('mision',
                e.currentTarget.value || null)
            }
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Switch
            label="Es jefe de unidad"
            checked={form.values.es_jefe}
            onChange={(e) =>
              form.setFieldValue('es_jefe', e.currentTarget.checked)
            }
            color="emerald"
            mt="xs"
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Switch
            label="Puesto activo"
            checked={form.values.activo}
            onChange={(e) =>
              form.setFieldValue('activo', e.currentTarget.checked)
            }
            color="emerald"
            mt="xs"
          />
        </Grid.Col>
      </Grid>
    </form>
  )
}
