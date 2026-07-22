'use client'

import { Modal, Button, Group, Stack, Select, TextInput, Textarea, SegmentedControl } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { usePuestos } from '@/features/estructura/hooks/usePuestos'
import { useServidores } from '../hooks/useServidores'
import { useSubrogacionMutations } from '../hooks/useSubrogacionMutations'
import {
  subrogacionSchema, type SubrogacionFormData,
} from '../schemas/subrogacion.schema'
import type { UnidadConRelaciones, PuestoConRelaciones, ServidorConRelaciones } from '@/types/api'

const TIPO_OPTIONS = [
  { value: 'subrogacion', label: 'Subrogación' },
  { value: 'encargo',     label: 'Encargo' },
]

const MOTIVO_OPTIONS = [
  { value: 'vacaciones',          label: 'Vacaciones' },
  { value: 'comision_servicios',  label: 'Comisión de Servicios' },
  { value: 'enfermedad',          label: 'Enfermedad' },
  { value: 'licencia',            label: 'Licencia' },
  { value: 'encargo_vacante',     label: 'Encargo por Vacante' },
  { value: 'otro',                label: 'Otro' },
]

const BLANK_VALUES: SubrogacionFormData = {
  tipo: 'subrogacion',
  servidor_subrogante_id: undefined as unknown as number,
  servidor_subrogado_id:  null,
  unidad_administrativa_id: undefined as unknown as number,
  puesto_subrogado_id:      undefined as unknown as number,
  fecha_inicio: '',
  fecha_fin:    '',
  motivo: 'vacaciones',
  resolucion_numero: '',
  observacion: '',
}

interface Props {
  opened: boolean
  onClose: () => void
}

export function SubrogacionModal({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { registrar } = useSubrogacionMutations()

  const {
    control, handleSubmit, reset, register, setValue,
    formState: { errors },
  } = useForm<SubrogacionFormData>({
    resolver: zodResolver(subrogacionSchema),
    defaultValues: BLANK_VALUES,
  })

  const tipo = useWatch({ control, name: 'tipo' })
  const unidadSelId = useWatch({ control, name: 'unidad_administrativa_id' })

  const { data: unidadesRaw } = useTodasUnidades({ nivel: 2 })
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[]

  const { data: puestosData } = usePuestos(
    unidadSelId ? { unidad_administrativa_id: Number(unidadSelId), per_page: 100 } : undefined
  )
  const puestos = (puestosData?.data ?? []) as PuestoConRelaciones[]

  const { data: servidoresData } = useServidores({ per_page: 500 })
  const servidores = (servidoresData?.data ?? []) as ServidorConRelaciones[]

  const unidadOptions = unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))
  const puestoOptions = puestos.map((p) => ({ value: String(p.id), label: p.cargo?.nombre ?? `Puesto ${p.id}` }))
  const servidorOptions = servidores.map((s) => ({
    value: String(s.id),
    label: `${[s.apellido, s.nombre].filter(Boolean).join(' ')} — ${s.cedula}`,
  }))

  const handleClose = () => {
    reset(BLANK_VALUES)
    onClose()
  }

  const toDate = (v?: string | null): Date | null => {
    if (!v) return null
    const [year, month, day] = v.split('T')[0].split('-').map(Number)
    return new Date(year, month - 1, day)
  }
  const fromDate = (d: Date | string | null): string | null => {
    if (!d) return null
    const date = typeof d === 'string' ? toDate(d) : d
    if (!date || isNaN(date.getTime())) return null
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
  }

  const onSubmit = (values: SubrogacionFormData) => {
    registrar.mutate(values, { onSuccess: handleClose })
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Nueva subrogación / encargo"
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Controller
            name="tipo"
            control={control}
            render={({ field }) => (
              <SegmentedControl
                data={TIPO_OPTIONS}
                value={field.value}
                onChange={(v) => {
                  field.onChange(v)
                  if (v === 'encargo') setValue('servidor_subrogado_id', null)
                }}
                fullWidth
              />
            )}
          />

          <Controller
            name="servidor_subrogante_id"
            control={control}
            render={({ field }) => (
              <Select
                label={tipo === 'encargo' ? 'Servidor encargado' : 'Servidor subrogante'}
                placeholder="Seleccionar servidor"
                data={servidorOptions}
                searchable
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                error={errors.servidor_subrogante_id?.message}
              />
            )}
          />

          {tipo === 'subrogacion' && (
            <Controller
              name="servidor_subrogado_id"
              control={control}
              render={({ field }) => (
                <Select
                  label="Servidor titular a subrogar"
                  placeholder="Seleccionar servidor"
                  data={servidorOptions}
                  searchable
                  clearable
                  {...contained}
                  value={field.value ? String(field.value) : null}
                  onChange={(v) => field.onChange(v ? Number(v) : null)}
                  error={errors.servidor_subrogado_id?.message}
                />
              )}
            />
          )}

          <Controller
            name="unidad_administrativa_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Unidad administrativa"
                placeholder="Seleccionar unidad"
                data={unidadOptions}
                searchable
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => {
                  field.onChange(v ? Number(v) : undefined)
                  setValue('puesto_subrogado_id', undefined as unknown as number)
                }}
                error={errors.unidad_administrativa_id?.message}
              />
            )}
          />

          <Controller
            name="puesto_subrogado_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Puesto"
                placeholder={unidadSelId ? 'Seleccionar puesto' : 'Seleccione primero la unidad'}
                data={puestoOptions}
                searchable
                disabled={!unidadSelId}
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                error={errors.puesto_subrogado_id?.message}
              />
            )}
          />

          <Group grow>
            <Controller
              name="fecha_inicio"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Fecha de inicio"
                  placeholder="Seleccionar fecha"
                  valueFormat="YYYY-MM-DD"
                  {...contained}
                  value={toDate(field.value)}
                  onChange={(d) => field.onChange(fromDate(d) ?? '')}
                  error={errors.fecha_inicio?.message}
                />
              )}
            />
            <Controller
              name="fecha_fin"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Fecha de fin"
                  placeholder="Seleccionar fecha"
                  valueFormat="YYYY-MM-DD"
                  {...contained}
                  value={toDate(field.value)}
                  onChange={(d) => field.onChange(fromDate(d) ?? '')}
                  error={errors.fecha_fin?.message}
                />
              )}
            />
          </Group>

          <Controller
            name="motivo"
            control={control}
            render={({ field }) => (
              <Select
                label="Motivo"
                data={MOTIVO_OPTIONS}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? 'otro')}
                error={errors.motivo?.message}
              />
            )}
          />

          <TextInput
            label="Número de resolución"
            placeholder="Opcional"
            {...contained}
            {...register('resolucion_numero')}
            error={errors.resolucion_numero?.message}
          />

          <Textarea
            label="Observación"
            placeholder="Opcional"
            minRows={2}
            {...contained}
            {...register('observacion')}
            error={errors.observacion?.message}
          />

          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>Cancelar</Button>
            <Button type="submit" color="emerald" variant="light" loading={registrar.isPending}>
              Registrar
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
