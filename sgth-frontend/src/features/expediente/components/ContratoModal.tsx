'use client'

import { Modal, Button, Group, Stack, Select,
         TextInput, Grid, Badge } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useUnidades } from '@/features/estructura/hooks/useUnidades'
import { usePuestos } from '@/features/estructura/hooks/usePuestos'
import { useContratoMutations } from '../hooks/useContratoMutations'
import { contratoSchema, type ContratoFormData }
  from '../schemas/contrato.schema'
import type { UnidadConRelaciones, PuestoConRelaciones } from '@/types/api'
import { useState } from 'react'

const TIPO_OPTIONS = [
  { value: 'nombramiento_permanente',     label: 'Nombramiento permanente' },
  { value: 'nombramiento_provisional',    label: 'Nombramiento provisional' },
  { value: 'servicios_ocasionales',       label: 'Servicios ocasionales' },
  { value: 'libre_nombramiento_remocion', label: 'Libre nombramiento y remoción' },
  { value: 'codigo_trabajo',              label: 'Código del Trabajo' },
  { value: 'servicios_profesionales',     label: 'Servicios profesionales' },
]

const ESTADO_OPTIONS = [
  { value: 'vigente',   label: 'Vigente' },
  { value: 'terminado', label: 'Terminado' },
  { value: 'cancelado', label: 'Cancelado' },
]

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
}

export function ContratoModal({ opened, onClose, servidorId }: Props) {
  const { isMobile }  = useMobileBreakpoint()
  const contained     = useContainedInput()
  const { crear }     = useContratoMutations(servidorId)

  // Unidades solo nivel 2
  const { data: unidadesRaw } = useUnidades({ nivel: 2 })
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[]

  // ID de unidad seleccionada para filtrar puestos
  const [unidadSelId, setUnidadSelId] = useState<number | null>(null)

  // Puestos filtrados por unidad seleccionada
  const { data: puestosData } = usePuestos(
    unidadSelId
      ? { unidad_administrativa_id: unidadSelId, per_page: 100 }
      : undefined
  )
  const puestos = (puestosData?.data ?? []) as PuestoConRelaciones[]

  const unidadOptions = unidades.map(u => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }))

  const puestoOptions = puestos.map(p => ({
    value: String(p.id),
    label: p.cargo?.nombre ?? `Puesto ${p.id}`,
    description: p.rmu ? `RMU: $${Number(p.rmu).toFixed(2)}` : undefined,
  }))

  const {
    register,
    control,
    handleSubmit,
    reset,
    setValue,
    formState: { errors },
  } = useForm<ContratoFormData>({
    resolver: zodResolver(contratoSchema),
    defaultValues: {
      tipo_nombramiento:        '',
      numero_contrato:          '',
      unidad_administrativa_id: undefined,
      puesto_id:                undefined,
      fecha_inicio:             '',
      fecha_fin:                null,
      resolucion_numero:        '',
      codigo_marcacion:         '',
      estado:                   'vigente',
    },
  })

  const handleClose = () => {
    reset()
    setUnidadSelId(null)
    onClose()
  }

  const toDate = (v?: string | null): Date | null => {
    if (!v) return null
    const datePart = v.split('T')[0]
    const [year, month, day] = datePart.split('-').map(Number)
    return new Date(year, month - 1, day)
  }

  const fromDate = (d: Date | string | null): string | null => {
    if (!d) return null
    const date = typeof d === 'string' ? toDate(d) : d
    if (!date || isNaN(date.getTime())) return null
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  const onSubmit = (values: ContratoFormData) => {
    crear.mutateAsync(values)
      .then(handleClose)
      .catch(() => {})
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Registrar contrato"
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Grid>
            {/* Tipo de nombramiento */}
            <Grid.Col span={12}>
              <Controller
                name="tipo_nombramiento"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Tipo de nombramiento"
                    placeholder="Seleccionar tipo"
                    data={TIPO_OPTIONS}
                    searchable
                    {...contained}
                    value={field.value}
                    onChange={(v) => field.onChange(v ?? '')}
                    error={errors.tipo_nombramiento?.message}
                  />
                )}
              />
            </Grid.Col>

            {/* Unidad administrativa — solo nivel 2 */}
            <Grid.Col span={{ base: 12, sm: 6 }}>
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
                    onChange={(v) => {
                      const id = v ? Number(v) : undefined
                      field.onChange(id)
                      setUnidadSelId(id ?? null)
                      // Limpiar puesto al cambiar unidad
                      setValue('puesto_id', undefined as unknown as number)
                    }}
                    error={errors.unidad_administrativa_id?.message}
                  />
                )}
              />
            </Grid.Col>

            {/* Puesto — filtrado por unidad */}
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="puesto_id"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Puesto"
                    placeholder={
                      unidadSelId
                        ? puestoOptions.length === 0
                          ? 'Sin puestos disponibles'
                          : 'Seleccionar puesto'
                        : 'Seleccione primero la unidad'
                    }
                    data={puestoOptions}
                    searchable
                    disabled={!unidadSelId}
                    {...contained}
                    value={field.value ? String(field.value) : ''}
                    onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                    error={errors.puesto_id?.message}
                  />
                )}
              />
            </Grid.Col>

            {/* Número de contrato */}
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <TextInput
                label="Número de contrato"
                placeholder="Ej: CONT-2024-001 (opcional)"
                {...contained}
                {...register('numero_contrato')}
                error={errors.numero_contrato?.message}
              />
            </Grid.Col>

            {/* Número de resolución */}
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <TextInput
                label="Número de resolución"
                placeholder="Ej: RES-2024-001 (opcional)"
                {...contained}
                {...register('resolucion_numero')}
                error={errors.resolucion_numero?.message}
              />
            </Grid.Col>

            {/* Fecha de inicio */}
            <Grid.Col span={{ base: 12, sm: 6 }}>
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
            </Grid.Col>

            {/* Fecha de fin */}
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="fecha_fin"
                control={control}
                render={({ field }) => (
                  <DatePickerInput
                    label="Fecha de fin"
                    placeholder="Opcional — se puede definir después"
                    valueFormat="YYYY-MM-DD"
                    clearable
                    {...contained}
                    value={toDate(field.value)}
                    onChange={(d) => field.onChange(fromDate(d))}
                    error={errors.fecha_fin?.message}
                  />
                )}
              />
            </Grid.Col>

            {/* Código de marcación */}
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <TextInput
                label="Código de marcación biométrica"
                placeholder="Código del biométrico (opcional)"
                maxLength={10}
                {...contained}
                {...register('codigo_marcacion')}
                error={errors.codigo_marcacion?.message}
              />
            </Grid.Col>

            {/* Estado del contrato */}
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="estado"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Estado del contrato"
                    data={ESTADO_OPTIONS}
                    {...contained}
                    value={field.value}
                    onChange={(v) =>
                      field.onChange(
                        (v ?? 'vigente') as ContratoFormData['estado']
                      )
                    }
                    error={errors.estado?.message}
                  />
                )}
              />
            </Grid.Col>
          </Grid>

          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              variant="light"
              loading={crear.isPending}
            >
              Registrar contrato
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
