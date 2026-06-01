'use client'

import { useState, useEffect } from 'react'
import {
  Modal, Button, Group, Stack, Select,
  NumberInput, Textarea, Grid, Text,
  Stepper, Alert, Badge, Divider,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { notifications } from '@mantine/notifications'
import {
  IconCheck, IconInfoCircle,
  IconFileDownload, IconAlertTriangle,
} from '@tabler/icons-react'
import React from 'react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useUnidades } from '@/features/estructura/hooks/useUnidades'
import { useServidores } from '@/features/expediente/hooks/useServidores'
import { usePeriodosVacaciones } from '../hooks/usePeriodosVacaciones'
import { useVacacionMutations } from '../hooks/useVacacionMutations'
import { asistenciaService } from '../services/asistenciaService'
import type {
  UnidadConRelaciones,
  ServidorConRelaciones,
  Vacacion,
} from '@/types/api'

const MOTIVO_OPTIONS = [
  { value: 'vacaciones_anuales',
    label: 'Vacaciones Anuales (mayor a 5 días)' },
  { value: 'permiso_cargo_vacaciones',
    label: 'Permiso con Cargo a Vacaciones (máx. 5 días)' },
  { value: 'licencia_sin_goce',
    label: 'Licencia sin Goce de Haberes' },
  { value: 'matrimonio',
    label: 'Matrimonio' },
  { value: 'capacitacion',
    label: 'Capacitación y/o Adiestramiento' },
  { value: 'enfermedad',
    label: 'Enfermedad' },
  { value: 'maternidad',
    label: 'Maternidad' },
  { value: 'paternidad',
    label: 'Paternidad' },
  { value: 'estudios_sin_remuneracion',
    label: 'Estudios sin Remuneración' },
  { value: 'calamidad_domestica',
    label: 'Calamidad Doméstica' },
  { value: 'licencia_con_goce',
    label: 'Licencia con Goce de Sueldo' },
]

// Motivos que descuentan del saldo de vacaciones
const MOTIVOS_DESCUENTO = ['vacaciones_anuales', 'permiso_cargo_vacaciones']

const schema = z.object({
  unidad_administrativa_id: z.number({
    error: 'Seleccione la unidad'
  }),
  servidor_id:         z.number({ error: 'Seleccione el servidor' }),
  jefe_id:             z.number().optional().nullable(),
  persona_reemplaza_id: z.number().optional().nullable(),
  motivo:              z.string().min(1, 'Seleccione el motivo'),
  fecha_inicio:        z.string().min(1, 'Requerido'),
  fecha_fin:           z.string().min(1, 'Requerido'),
  fecha_retorno:       z.string().optional().nullable(),
  dias_solicitados:    z.number().min(1, 'Mínimo 1 día'),
  tipo_dias:           z.enum(['habiles', 'calendario']),
  observacion:         z.string().optional().nullable(),
})

type FormData = z.infer<typeof schema>

const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const [y, m, d] = v.split('-').map(Number)
  return new Date(y, m - 1, d)
}
const fromDate = (d: any): string | null => {
  if (!d) return null
  if (typeof d === 'string') return d.substring(0, 10)
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
}

interface Props {
  opened:    boolean
  onClose:   () => void
  isAdmin?:  boolean
}

export function VacacionModal({ opened, onClose, isAdmin = true }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained    = useContainedInput()
  const { crear }    = useVacacionMutations()

  const [paso, setPaso]                   = useState(0)
  const [vacacionCreada, setVacacionCreada] =
    useState<Vacacion | null>(null)
  const [exportando, setExportando]       = useState(false)
  const [servidorSelId, setServidorSelId] = useState<number | null>(null)
  const [unidadSelId, setUnidadSelId]     = useState<number | null>(null)

  // Datos
  const { data: unidadesRaw } = useUnidades({ nivel: 2 })
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[]

  const { data: servidoresData } = useServidores({ per_page: 200 })
  const todosServidores = (servidoresData?.data ?? []) as ServidorConRelaciones[]

  const servidoresUnidad = unidadSelId
    ? todosServidores.filter(s =>
        Number(s.unidad_administrativa?.id) === unidadSelId
      )
    : []

  // Saldo del servidor seleccionado
  const { data: resumenPeriodos } =
    usePeriodosVacaciones(servidorSelId)

  const saldoDisponible = resumenPeriodos?.saldo_total ?? 0
  const alertaLimite    = resumenPeriodos?.alerta_limite ?? false

  const unidadOptions = unidades.map(u => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }))

  const servidorOptions = servidoresUnidad.map(s => ({
    value: String(s.id),
    label: `${[s.apellido, s.nombre].filter(Boolean).join(' ')} — ${s.cedula}`,
  }))

  const jefeOptions = servidoresUnidad
    .filter(s => (s.puesto as { es_jefe?: boolean } | null)?.es_jefe)
    .map(s => ({
      value: String(s.id),
      label: [s.apellido, s.nombre].filter(Boolean).join(' '),
    }))

  const reemplazaOptions = servidoresUnidad
    .map(s => ({
      value: String(s.id),
      label: `${[s.apellido, s.nombre].filter(Boolean).join(' ')} — ${s.cedula}`,
    }))

  const {
    control, handleSubmit, reset, register,
    watch, setValue,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      unidad_administrativa_id: undefined,
      servidor_id:              undefined,
      jefe_id:                  null,
      persona_reemplaza_id:     null,
      motivo:                   '',
      fecha_inicio:             '',
      fecha_fin:                '',
      fecha_retorno:            null,
      dias_solicitados:         1,
      tipo_dias:                'habiles',
      observacion:              '',
    },
  })

  const motivoWatch = watch('motivo')
  const descuentaVacaciones = MOTIVOS_DESCUENTO.includes(motivoWatch)

  const handleClose = () => {
    reset()
    setUnidadSelId(null)
    setServidorSelId(null)
    setPaso(0)
    setVacacionCreada(null)
    onClose()
  }

  const onSubmit = async (values: FormData) => {
    const result = await crear.mutateAsync(
      values as Record<string, unknown>
    )
    setVacacionCreada(result as unknown as Vacacion)
    setPaso(1)
  }

  const handleExportar = async () => {
    if (!vacacionCreada) return
    setExportando(true)
    try {
      const blob = await asistenciaService.vacaciones.exportar(
        Number(vacacionCreada.id)
      )
      const url  = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href  = url
      link.download = `vacacion_${vacacionCreada.folio ?? vacacionCreada.id}.pdf`
      link.click()
      URL.revokeObjectURL(url)
      notifications.show({
        title:   'PDF descargado',
        message: 'La solicitud fue exportada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
    } catch {
      notifications.show({
        title:   'Error',
        message: 'No se pudo exportar el PDF.',
        color:   'red',
      })
    } finally {
      setExportando(false)
    }
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Solicitud de vacaciones / permiso"
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stepper active={paso} mb="lg" size="sm">
        <Stepper.Step label="Datos de la solicitud" />
        <Stepper.Step label="Confirmación" />
      </Stepper>

      {/* ── PASO 0: Formulario ── */}
      {paso === 0 && (
        <form onSubmit={handleSubmit(onSubmit)}>
          <Stack gap="sm">

            {/* Unidad */}
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
                    const id = v ? Number(v) : undefined
                    field.onChange(id)
                    setUnidadSelId(id ?? null)
                    setValue('servidor_id', undefined as unknown as number)
                    setValue('jefe_id', null)
                    setValue('persona_reemplaza_id', null)
                    setServidorSelId(null)
                  }}
                  error={errors.unidad_administrativa_id?.message}
                />
              )}
            />

            <Grid>
              {/* Servidor */}
              <Grid.Col span={{ base: 12, sm: 6 }}>
                <Controller
                  name="servidor_id"
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Servidor"
                      placeholder={
                        !unidadSelId
                          ? 'Seleccione primero la unidad'
                          : 'Seleccionar servidor'
                      }
                      data={servidorOptions}
                      searchable
                      disabled={!unidadSelId}
                      {...contained}
                      value={field.value ? String(field.value) : null}
                      onChange={(v) => {
                        const id = v ? Number(v) : undefined
                        field.onChange(id)
                        setServidorSelId(id ?? null)
                      }}
                      error={errors.servidor_id?.message}
                    />
                  )}
                />
              </Grid.Col>

              {/* Jefe */}
              <Grid.Col span={{ base: 12, sm: 6 }}>
                <Controller
                  name="jefe_id"
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Jefe inmediato"
                      placeholder={
                        !unidadSelId
                          ? 'Seleccione primero la unidad'
                          : 'Seleccionar jefe'
                      }
                      data={jefeOptions}
                      searchable clearable
                      disabled={!unidadSelId}
                      {...contained}
                      value={field.value ? String(field.value) : null}
                      onChange={(v) =>
                        field.onChange(v ? Number(v) : null)
                      }
                      error={errors.jefe_id?.message}
                    />
                  )}
                />
              </Grid.Col>

              {/* Persona que reemplaza */}
              <Grid.Col span={{ base: 12, sm: 6 }}>
                <Controller
                  name="persona_reemplaza_id"
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Persona que reemplaza"
                      placeholder="Seleccionar (opcional)"
                      data={reemplazaOptions}
                      searchable clearable
                      disabled={!unidadSelId}
                      {...contained}
                      value={field.value ? String(field.value) : null}
                      onChange={(v) =>
                        field.onChange(v ? Number(v) : null)
                      }
                    />
                  )}
                />
              </Grid.Col>

              {/* Motivo */}
              <Grid.Col span={{ base: 12, sm: 6 }}>
                <Controller
                  name="motivo"
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Motivo"
                      placeholder="Seleccionar motivo"
                      data={MOTIVO_OPTIONS}
                      searchable
                      {...contained}
                      value={field.value}
                      onChange={(v) => field.onChange(v ?? '')}
                      error={errors.motivo?.message}
                    />
                  )}
                />
              </Grid.Col>
            </Grid>

            {/* Saldo disponible */}
            {servidorSelId && (
              <Alert
                icon={alertaLimite
                  ? <IconAlertTriangle size={16} />
                  : <IconInfoCircle size={16} />}
                color={alertaLimite ? 'orange' : 'blue'}
                variant="light"
              >
                <Group gap="sm">
                  <Text size="sm">
                    Saldo disponible de vacaciones:
                  </Text>
                  <Badge
                    color={alertaLimite ? 'orange' : 'emerald'}
                    size="lg"
                  >
                    {Number(saldoDisponible).toFixed(1)} días
                  </Badge>
                  {alertaLimite && (
                    <Text size="xs" c="orange">
                      ⚠️ Se acerca al límite máximo de acumulación
                    </Text>
                  )}
                </Group>
                {!descuentaVacaciones && motivoWatch && (
                  <Text size="xs" c="dimmed" mt={4}>
                    Este motivo no descuenta del saldo de vacaciones.
                  </Text>
                )}
              </Alert>
            )}

            <Divider label="Fechas" labelPosition="left" />

            <Grid>
              <Grid.Col span={{ base: 12, sm: 4 }}>
                <Controller
                  name="fecha_inicio"
                  control={control}
                  render={({ field }) => (
                    <DatePickerInput
                      label="Fecha inicio"
                      placeholder="Seleccionar"
                      valueFormat="YYYY-MM-DD"
                      {...contained}
                      value={toDate(field.value)}
                      onChange={(d: any) =>
                        field.onChange(fromDate(d) ?? '')
                      }
                      error={errors.fecha_inicio?.message}
                    />
                  )}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, sm: 4 }}>
                <Controller
                  name="fecha_fin"
                  control={control}
                  render={({ field }) => (
                    <DatePickerInput
                      label="Fecha fin"
                      placeholder="Seleccionar"
                      valueFormat="YYYY-MM-DD"
                      {...contained}
                      value={toDate(field.value)}
                      onChange={(d: any) =>
                        field.onChange(fromDate(d) ?? '')
                      }
                      error={errors.fecha_fin?.message}
                    />
                  )}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, sm: 4 }}>
                <Controller
                  name="fecha_retorno"
                  control={control}
                  render={({ field }) => (
                    <DatePickerInput
                      label="Fecha de retorno"
                      placeholder="Opcional"
                      valueFormat="YYYY-MM-DD"
                      clearable
                      {...contained}
                      value={toDate(field.value)}
                      onChange={(d: any) =>
                        field.onChange(fromDate(d))
                      }
                    />
                  )}
                />
              </Grid.Col>

              <Grid.Col span={{ base: 12, sm: 4 }}>
                <Controller
                  name="dias_solicitados"
                  control={control}
                  render={({ field }) => (
                    <NumberInput
                      label="Días solicitados"
                      min={1} max={365}
                      {...contained}
                      value={field.value}
                      onChange={(v) =>
                        field.onChange(typeof v === 'number' ? v : 1)
                      }
                      error={errors.dias_solicitados?.message}
                    />
                  )}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, sm: 4 }}>
                <Controller
                  name="tipo_dias"
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Tipo de días"
                      data={[
                        { value: 'habiles',    label: 'Hábiles' },
                        { value: 'calendario', label: 'Calendario' },
                      ]}
                      {...contained}
                      value={field.value}
                      onChange={(v) =>
                        field.onChange(
                          (v ?? 'habiles') as FormData['tipo_dias']
                        )
                      }
                    />
                  )}
                />
              </Grid.Col>
            </Grid>

            <Textarea
              label="Observación"
              placeholder="Observaciones adicionales (opcional)"
              rows={2}
              {...contained}
              {...register('observacion')}
            />

            <Group justify="flex-end" mt="md">
              <Button variant="default" onClick={handleClose}>
                Cancelar
              </Button>
              <Button
                type="submit"
                color="emerald"
                variant="light"
                loading={isSubmitting}
              >
                Registrar solicitud
              </Button>
            </Group>
          </Stack>
        </form>
      )}

      {/* ── PASO 1: Confirmación ── */}
      {paso === 1 && vacacionCreada && (
        <Stack gap="md" align="center">
          <Alert
            icon={<IconCheck size={20} />}
            color="emerald"
            variant="light"
            w="100%"
          >
            <Text fw={600}>Solicitud registrada correctamente</Text>
            <Text size="sm" mt={4}>
              Folio: <strong>{vacacionCreada.folio ?? '—'}</strong>
            </Text>
          </Alert>

          <Text size="sm" c="dimmed" ta="center">
            ¿Desea exportar la solicitud en PDF?
          </Text>
          <Group justify="center">
            <Button
              variant="light" color="blue"
              leftSection={<IconFileDownload size={16} />}
              loading={exportando}
              onClick={handleExportar}
            >
              Exportar PDF
            </Button>
            <Button variant="default" onClick={handleClose}>
              Cerrar
            </Button>
          </Group>
        </Stack>
      )}
    </Modal>
  )
}
