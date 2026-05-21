'use client'

import { TextInput, Select, Grid } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useUnidades } from '@/features/estructura/hooks/useUnidades'
import { usePuestos } from '@/features/estructura/hooks/usePuestos'
import type { UseFormReturnType } from '@mantine/form'
import type { ServidorFormData } from '../schemas/servidor.schema'
import type { UnidadConRelaciones, Puesto } from '@/types/api'

const REGIMEN_OPTIONS = [
  { value: 'losep',          label: 'LOSEP' },
  { value: 'codigo_trabajo', label: 'Código del Trabajo' },
]

const TIPO_NOMBRAMIENTO_OPTIONS = [
  { value: 'nombramiento_permanente',     label: 'Nombramiento Permanente' },
  { value: 'nombramiento_provisional',    label: 'Nombramiento Provisional' },
  { value: 'servicios_ocasionales',       label: 'Servicios Ocasionales' },
  { value: 'libre_nombramiento_remocion', label: 'Libre Nombramiento y Remoción' },
  { value: 'codigo_trabajo',              label: 'Código del Trabajo' },
  { value: 'servicios_profesionales',     label: 'Servicios Profesionales' },
]

interface Props {
  form: UseFormReturnType<ServidorFormData>
}

export function ServidorFormLaboral({ form }: Props) {
  const contained = useContainedInput()
  const { data: unidades = [] } = useUnidades()
  const { data: puestosData }   = usePuestos()

  const unidadOptions = (unidades as UnidadConRelaciones[]).map(u => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }))

  type PuestoConNombre = Puesto & { nombre?: string }
  const puestoOptions = (puestosData?.data ?? [] as PuestoConNombre[])
    .map((p: PuestoConNombre) => ({
      value: String(p.id),
      label: p.nombre ?? `Puesto ${p.id}`,
    }))

  const toDate = (v?: string | null) =>
    v ? new Date(v) : null

  const fromDate = (d: any) => {
    if (!d) return null
    const date = new Date(d)
    return isNaN(date.getTime()) ? null : date.toISOString().split('T')[0]
  }

  return (
    <Grid>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Select
          label="Régimen laboral"
          placeholder="Seleccionar régimen"
          data={REGIMEN_OPTIONS}
          {...contained}
          value={form.values.regimen_laboral ?? ''}
          onChange={(v) => form.setFieldValue('regimen_laboral',
            v as ServidorFormData['regimen_laboral'])}
          error={form.errors.regimen_laboral}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Select
          label="Tipo de nombramiento"
          placeholder="Seleccionar tipo"
          data={TIPO_NOMBRAMIENTO_OPTIONS}
          searchable
          {...contained}
          value={form.values.tipo_nombramiento ?? ''}
          onChange={(v) => form.setFieldValue('tipo_nombramiento',
            v as ServidorFormData['tipo_nombramiento'])}
          error={form.errors.tipo_nombramiento}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Select
          label="Unidad administrativa"
          placeholder="Seleccionar unidad"
          data={unidadOptions}
          searchable
          {...contained}
          value={form.values.unidad_administrativa_id
            ? String(form.values.unidad_administrativa_id) : ''}
          onChange={(v) =>
            form.setFieldValue('unidad_administrativa_id',
              v ? Number(v) : '' as unknown as number)}
          error={form.errors.unidad_administrativa_id}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Select
          label="Puesto"
          placeholder="Seleccionar puesto"
          data={puestoOptions}
          searchable
          {...contained}
          value={form.values.puesto_id
            ? String(form.values.puesto_id) : ''}
          onChange={(v) =>
            form.setFieldValue('puesto_id',
              v ? Number(v) : '' as unknown as number)}
          error={form.errors.puesto_id}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <DatePickerInput
          label="Fecha de ingreso a la institución"
          placeholder="Seleccionar fecha"
          valueFormat="YYYY-MM-DD"
          {...contained}
          value={toDate(form.values.fecha_ingreso_institucion)}
          onChange={(d) =>
            form.setFieldValue('fecha_ingreso_institucion',
              fromDate(d) ?? '')
          }
          error={form.errors.fecha_ingreso_institucion}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <DatePickerInput
          label="Fecha ingreso sector público"
          placeholder="Seleccionar fecha (opcional)"
          valueFormat="YYYY-MM-DD"
          clearable
          {...contained}
          value={toDate(form.values.fecha_ingreso_sector_publico)}
          onChange={(d) =>
            form.setFieldValue('fecha_ingreso_sector_publico', fromDate(d))
          }
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <DatePickerInput
          label="Fecha de nombramiento"
          placeholder="Seleccionar fecha (opcional)"
          valueFormat="YYYY-MM-DD"
          clearable
          {...contained}
          value={toDate(form.values.fecha_nombramiento)}
          onChange={(d) =>
            form.setFieldValue('fecha_nombramiento', fromDate(d))
          }
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Número de contrato"
          placeholder="Opcional"
          {...contained}
          {...form.getInputProps('numero_contrato')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <DatePickerInput
          label="Inicio último contrato"
          placeholder="Seleccionar fecha (opcional)"
          valueFormat="YYYY-MM-DD"
          clearable
          {...contained}
          value={toDate(form.values.fecha_inicio_ultimo_contrato)}
          onChange={(d) =>
            form.setFieldValue('fecha_inicio_ultimo_contrato', fromDate(d))
          }
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <DatePickerInput
          label="Fin último contrato"
          placeholder="Seleccionar fecha (opcional)"
          valueFormat="YYYY-MM-DD"
          clearable
          {...contained}
          value={toDate(form.values.fecha_fin_ultimo_contrato)}
          onChange={(d) =>
            form.setFieldValue('fecha_fin_ultimo_contrato', fromDate(d))
          }
        />
      </Grid.Col>
    </Grid>
  )
}
